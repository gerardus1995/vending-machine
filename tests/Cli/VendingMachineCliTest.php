<?php

declare(strict_types=1);

namespace App\Tests\Cli;

use App\Cli\VendingMachineCli;
use App\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class VendingMachineCliTest extends TestCase
{
    private const STOCK = ['water' => 5, 'juice' => 5, 'soda' => 5];
    private const FUND = [5 => 10, 10 => 10, 25 => 10, 100 => 10];

    private VendingMachine $machine;
    private VendingMachineCli $cli;

    protected function setUp(): void
    {
        $this->machine = new VendingMachine();
        $this->machine->service(self::STOCK, self::FUND);
        $this->cli = new VendingMachineCli($this->machine);
    }

    public function testChallengeExample1ExactPurchase(): void
    {
        self::assertSame(['SODA'], $this->respond(['1', '0.25', '0.25', 'GET-SODA']));
    }

    public function testChallengeExample2ReturnCoin(): void
    {
        self::assertSame(['0.10, 0.10'], $this->respond(['0.10', '0.10', 'RETURN-COIN']));
        self::assertSame(0, $this->machine->getInsertedTotal());
    }

    public function testChallengeExample3PurchaseWithChange(): void
    {
        self::assertSame(['WATER, 0.25, 0.10'], $this->respond(['1', 'GET-WATER']));
    }

    public function testInsertPrefixFormIsAccepted(): void
    {
        $this->respond(['INSERT 0.05', 'INSERT 1.00']);

        self::assertSame(105, $this->machine->getInsertedTotal());
    }

    public function testInsertingProducesNoImmediateResponse(): void
    {
        self::assertSame([], $this->respond(['0.25']));
    }

    public function testReturnCoinWithoutInsertedCoins(): void
    {
        self::assertSame(['(no coins inserted)'], $this->respond(['RETURN-COIN']));
    }

    public function testBlankLinesAreIgnored(): void
    {
        self::assertSame([], $this->cli->handleCommand('   '));
    }

    public function testInsufficientFundsIsRenderedAsError(): void
    {
        self::assertSame(
            ['ERROR: Insufficient funds for "Juice": inserted 0.25, price 1.00'],
            $this->respond(['0.25', 'GET-JUICE'])
        );
    }

    public function testOutOfStockIsRenderedAsError(): void
    {
        $this->machine->service(['water' => 1], self::FUND);
        $exactWaterPayment = ['0.25', '0.25', '0.10', '0.05'];
        $this->respond([...$exactWaterPayment, 'GET-WATER']);

        self::assertSame(
            ['ERROR: Product "Water" is out of stock'],
            $this->respond([...$exactWaterPayment, 'GET-WATER'])
        );
    }

    public function testMissingChangeIsRenderedAsError(): void
    {
        $this->machine->service(self::STOCK, []);

        self::assertSame(
            ['ERROR: Cannot make exact change of 0.35 from the available coins'],
            $this->respond(['1', 'GET-WATER'])
        );
    }

    public function testUnknownProductSelectorIsRejectedAsUnknownCommand(): void
    {
        self::assertSame(['ERROR: unknown command "GET-TEA"'], $this->respond(['GET-TEA']));
    }

    public function testUnsupportedCoinAmountIsRenderedAsError(): void
    {
        self::assertSame(
            ['ERROR: Unsupported coin denomination: 30 cents'],
            $this->respond(['0.30'])
        );
    }

    public function testNonNumericAmountIsTreatedAsUnknownCommand(): void
    {
        // The insert grammar only accepts numeric amounts, so non-numeric
        // input falls through to the unknown-command response.
        self::assertSame(['ERROR: unknown command "abc"'], $this->respond(['abc']));
    }

    public function testUnknownCommandIsRenderedAsError(): void
    {
        self::assertSame(['ERROR: unknown command "FOO"'], $this->respond(['FOO']));
    }

    public function testServiceAloneShowsUsage(): void
    {
        self::assertSame(
            ['USAGE: SERVICE water:5,juice:5,soda:5 5:10,10:10,25:10,100:10'],
            $this->respond(['SERVICE'])
        );
    }

    public function testServiceAppliesAndReplacesConfiguration(): void
    {
        self::assertSame(
            ['SERVICE APPLIED'],
            $this->respond(['SERVICE water:2,juice:0,soda:1 5:3,25:2'])
        );

        self::assertSame(['water' => 2, 'juice' => 0, 'soda' => 1], $this->machine->getProductStock());
        self::assertSame([5 => 3, 25 => 2], $this->machine->getCoinInventory());
    }

    public function testServiceIsRejectedWhileCoinsAreInserted(): void
    {
        $this->respond(['0.25']);

        self::assertSame(
            ['ERROR: Cannot service the machine while customer coins are inserted'],
            $this->respond(['SERVICE water:1 5:1'])
        );
    }

    public function testServiceWithUnknownProductIsRenderedAsError(): void
    {
        self::assertSame(
            ['ERROR: Unknown product "cola" in service configuration'],
            $this->respond(['SERVICE cola:1 5:1'])
        );
    }

    public function testMalformedServiceSpecificationIsRenderedAsError(): void
    {
        self::assertSame(
            ['ERROR: invalid service specification "water 5:1"'],
            $this->respond(['SERVICE water 5:1'])
        );
    }

    /**
     * @return list<string>
     */
    private function respond(array $commands): array
    {
        $responses = [];
        foreach ($commands as $command) {
            $responses = [...$responses, ...$this->cli->handleCommand($command)];
        }

        return $responses;
    }
}
