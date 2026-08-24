<?php

declare(strict_types=1);

namespace App\Cli;

use App\Application\InsertCoinAction;
use App\Application\PurchaseProductAction;
use App\Application\ReturnCoinAction;
use App\Application\ServiceMachineAction;
use App\Domain\Result\PurchaseResult;
use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;
use App\Domain\VendingMachine;

/**
 * Interactive console for the vending machine.
 *
 * Reads commands line by line from STDIN and renders responses using the
 * challenge vocabulary ("SODA", "0.25", ...). Domain exceptions are caught at
 * this presentation boundary and rendered as "ERROR: ..." lines; unexpected
 * throwables are not masked.
 *
 * Recognised commands (case-insensitive):
 *   1 | 0.25 | INSERT 0.05                          insert a coin
 *   RETURN-COIN                                     hand back the inserted coins
 *   GET-WATER | GET Juice | Soda                    dispense a product
 *   SERVICE water:5,juice:5,soda:5 5:10,25:10,...   replace stock and change fund
 */
final class VendingMachineCli
{
    private readonly InsertCoinAction $insertCoinAction;
    private readonly ReturnCoinAction $returnCoinAction;
    private readonly PurchaseProductAction $purchaseProductAction;
    private readonly ServiceMachineAction $serviceMachineAction;

    public function __construct(private readonly VendingMachine $vendingMachine)
    {
        $this->insertCoinAction = new InsertCoinAction($vendingMachine);
        $this->returnCoinAction = new ReturnCoinAction($vendingMachine);
        $this->purchaseProductAction = new PurchaseProductAction($vendingMachine);
        $this->serviceMachineAction = new ServiceMachineAction($vendingMachine);
    }

    public function run(): void
    {
        while (false !== ($line = fgets(\STDIN))) {
            foreach ($this->handleCommand($line) as $responseLine) {
                fwrite(\STDOUT, $responseLine.\PHP_EOL);
            }
        }
    }

    /**
     * Translates a single raw input line into zero or more response lines.
     *
     * @return list<string>
     */
    public function handleCommand(string $commandLine): array
    {
        $commandLine = trim($commandLine);

        if ('' === $commandLine) {
            return [];
        }

        try {
            return $this->dispatch($commandLine);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return [self::error($exception->getMessage())];
        }
    }

    /**
     * @return list<string>
     */
    private function dispatch(string $commandLine): array
    {
        if (1 === preg_match('/^(?:insert\s+)?(\d+(?:\.\d{1,2})?)$/i', $commandLine, $matches)) {
            $coin = Coin::fromCents(Money::fromString($matches[1])->cents());
            $this->insertCoinAction->execute($coin);

            return [];
        }

        if (1 === preg_match('/^return[-\s]?coin$/i', $commandLine)) {
            return $this->renderReturnedCoins();
        }

        if (1 === preg_match('/^(?:get[-\s])?(water|juice|soda)$/i', $commandLine, $matches)) {
            return $this->renderPurchase(
                $this->purchaseProductAction->execute(strtolower($matches[1]))
            );
        }

        if (1 === preg_match('/^service\s+(\S+)\s+(\S+)$/i', $commandLine, $matches)) {
            return $this->applyServiceConfiguration($matches[1], $matches[2]);
        }

        if (1 === preg_match('/^service$/i', $commandLine)) {
            return ['USAGE: SERVICE water:5,juice:5,soda:5 5:10,10:10,25:10,100:10'];
        }

        return [self::error(sprintf('unknown command "%s"', $commandLine))];
    }

    /**
     * @return list<string>
     */
    private function renderReturnedCoins(): array
    {
        $returnedCoins = $this->returnCoinAction->execute();

        if ([] === $returnedCoins) {
            return ['(no coins inserted)'];
        }

        return [implode(', ', self::renderCoins($returnedCoins))];
    }

    /**
     * @return list<string>
     */
    private function renderPurchase(PurchaseResult $purchaseResult): array
    {
        return [
            implode(', ', [
                strtoupper($purchaseResult->getProduct()->name()),
                ...self::renderCoins($purchaseResult->getChangeCoins()),
            ]),
        ];
    }

    /**
     * @param string $stockSpecification e.g. "water:5,juice:3,soda:2"
     * @param string $coinSpecification  e.g. "5:10,10:10,25:10,100:10"
     *
     * @return list<string>
     */
    private function applyServiceConfiguration(string $stockSpecification, string $coinSpecification): array
    {
        $productStock = self::parseQuantityPairs($stockSpecification);
        $parsedCoinQuantities = self::parseQuantityPairs($coinSpecification);

        if (null === $productStock || null === $parsedCoinQuantities) {
            return [self::error(sprintf(
                'invalid service specification "%s %s"',
                $stockSpecification,
                $coinSpecification
            ))];
        }

        $coinQuantities = [];
        foreach ($parsedCoinQuantities as $denomination => $quantity) {
            $coinQuantities[(int) $denomination] = $quantity;
        }

        $this->serviceMachineAction->execute($productStock, $coinQuantities);

        return ['SERVICE APPLIED'];
    }

    /**
     * Parses "id:quantity,id:quantity,..." specifications.
     *
     * @return null|array<string, int> null when the specification is malformed
     */
    private static function parseQuantityPairs(string $specification): ?array
    {
        $quantities = [];

        foreach (explode(',', $specification) as $pair) {
            $parts = explode(':', $pair);

            if (2 !== \count($parts) || '' === trim($parts[0]) || !ctype_digit($parts[1])) {
                return null;
            }

            $quantities[trim($parts[0])] = (int) $parts[1];
        }

        return $quantities;
    }

    /**
     * @param list<Coin> $coins
     *
     * @return list<string>
     */
    private static function renderCoins(array $coins): array
    {
        return array_map(
            static fn (Coin $coin): string => (string) Money::fromCents($coin->toCents()),
            $coins
        );
    }

    private static function error(string $message): string
    {
        return 'ERROR: '.$message;
    }
}
