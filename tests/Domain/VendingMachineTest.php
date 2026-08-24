<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Exception\InsufficientChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\InvalidCoinException;
use App\Domain\Exception\InvalidServiceOperationException;
use App\Domain\Exception\OutOfStockException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\ValueObject\Coin;
use App\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class VendingMachineTest extends TestCase
{
    private const DEFAULT_STOCK = ['water' => 5, 'juice' => 5, 'soda' => 5];
    private const FULL_FUND = [5 => 10, 10 => 10, 25 => 10, 100 => 10];

    private VendingMachine $machine;

    protected function setUp(): void
    {
        $this->machine = new VendingMachine();
    }

    public function testNewMachineHasNoStockNoFundAndNoInsertedMoney(): void
    {
        self::assertSame([], $this->machine->getProductStock());
        self::assertSame([], $this->machine->getCoinInventory());
        self::assertSame(0, $this->machine->getInsertedTotal());
    }

    public function testValidCoinsAreAcceptedIntoTheTransaction(): void
    {
        $this->insertCoins(Coin::ONE_HUNDRED, Coin::TWENTY_FIVE, Coin::TEN, Coin::FIVE);

        self::assertSame(140, $this->machine->getInsertedTotal());
    }

    public function testOnlySupportedDenominationsExist(): void
    {
        $this->expectException(InvalidCoinException::class);

        Coin::fromCents(30); // 30 cents is not a supported denomination
    }

    public function testReturnCoinGivesBackExactlyTheInsertedCoins(): void
    {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);
        $this->insertCoins(Coin::TEN, Coin::TEN);

        $returnedCoins = $this->machine->returnCoins();

        self::assertSame([Coin::TEN, Coin::TEN], $returnedCoins);
        self::assertSame(0, $this->machine->getInsertedTotal());
        // The machine's own fund must not be touched by RETURN-COIN.
        self::assertSame(self::FULL_FUND, $this->machine->getCoinInventory());
        self::assertSame([], $this->machine->returnCoins()); // transaction was cleared
    }

    public function testExactPaymentDispensesTheProductWithoutChange(): void
    {
        $this->machine->service(['water' => 1, 'juice' => 1, 'soda' => 1], []);
        $this->insertCoins(Coin::TWENTY_FIVE, Coin::TWENTY_FIVE, Coin::TEN, Coin::FIVE); // 65

        $result = $this->machine->selectProduct('water');

        self::assertSame('water', $result->getProduct()->id());
        self::assertSame('Water', $result->getProduct()->name());
        self::assertSame([], $result->getChangeCoins());
        self::assertTrue($result->getChangeTotal()->isZero());

        self::assertSame(0, $this->machine->getProductStock()['water']);
        // The inserted coins joined the machine's fund.
        self::assertSame([25 => 2, 10 => 1, 5 => 1], $this->machine->getCoinInventory());
        self::assertSame(0, $this->machine->getInsertedTotal());
    }

    public function testChangeConsistsOfActualCoinsFromTheFund(): void
    {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);

        $this->insertCoins(Coin::ONE_HUNDRED);
        $result = $this->machine->selectProduct('water'); // change due: 35

        self::assertSame([Coin::TWENTY_FIVE, Coin::TEN], $result->getChangeCoins());
        self::assertSame(35, $result->getChangeTotal()->cents());
    }

    public function testPurchaseConsumesFundCoinsAndAbsorbsTheInsertedOnes(): void
    {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);

        $this->insertCoins(Coin::ONE_HUNDRED, Coin::ONE_HUNDRED); // soda 150 -> change 50
        $this->machine->selectProduct('soda');

        $fund = $this->machine->getCoinInventory();
        self::assertSame(12, $fund[100]); // 10 initial + 2 inserted
        self::assertSame(8, $fund[25]);   // 10 - 2 returned as change
        self::assertSame(10, $fund[10]);
        self::assertSame(10, $fund[5]);
        self::assertSame(0, $this->machine->getInsertedTotal());
    }

    /**
     * @param array<string, int>       $productStock
     * @param array<int, int>          $coinQuantities
     * @param class-string<\Throwable> $expectedException
     *
     * @dataProvider invalidServiceConfigurationProvider
     */
    public function testInvalidServiceConfigurationLeavesMachineCompletelyUnchanged(
        string $reason,
        array $productStock,
        array $coinQuantities,
        string $expectedException
    ): void {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);
        $stateBefore = $this->currentState();

        $thrown = null;

        try {
            $this->machine->service($productStock, $coinQuantities);
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }

        self::assertNotNull($thrown, "Configuration '{$reason}' should have been rejected");
        self::assertInstanceOf($expectedException, $thrown);
        self::assertSame($stateBefore['stock'], $this->machine->getProductStock(), $reason);
        self::assertSame($stateBefore['fund'], $this->machine->getCoinInventory(), $reason);
        self::assertSame($stateBefore['inserted'], $this->machine->getInsertedTotal(), $reason);
    }

    /**
     * @return iterable<string, array{string, array<string, int>, array<int, int>, class-string<\Throwable>}>
     */
    public static function invalidServiceConfigurationProvider(): iterable
    {
        yield 'unknown product id' => [
            'unknown product id',
            ['cola' => 3],
            [],
            ProductNotFoundException::class,
        ];

        yield 'negative product stock' => [
            'negative product stock',
            ['water' => -1],
            [],
            \InvalidArgumentException::class,
        ];

        yield 'invalid coin denomination' => [
            'invalid coin denomination',
            [],
            [30 => 5],
            InvalidCoinException::class,
        ];

        yield 'negative coin quantity' => [
            'negative coin quantity',
            [],
            [25 => -2],
            \InvalidArgumentException::class,
        ];
    }

    public function testInsertedCustomerCoinsAreNeverUsedToMakeChange(): void
    {
        // A single quarter in the fund: on its own it cannot make the 35 change,
        // but combined with the customer's inserted coins it would be enough.
        $this->machine->service(self::DEFAULT_STOCK, [25 => 1]);
        $this->insertCoins(Coin::ONE_HUNDRED);

        try {
            $this->machine->selectProduct('water'); // change due 35 = fund's 25 + customer's (forbidden) 10
            self::fail('InsufficientChangeException was expected');
        } catch (InsufficientChangeException) {
            // expected
        }

        self::assertSame([25 => 1], $this->machine->getCoinInventory());
        self::assertSame(100, $this->machine->getInsertedTotal());
        self::assertSame(5, $this->machine->getProductStock()['water']);
    }

    public function testServiceReplacesStockAndFundInsteadOfAddingToThem(): void
    {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);

        $this->machine->service(['juice' => 2], [5 => 1]);

        self::assertSame(['juice' => 2], $this->machine->getProductStock());
        self::assertSame([5 => 1], $this->machine->getCoinInventory());
    }

    public function testServiceIsRejectedWhileCoinsAreInserted(): void
    {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);
        $this->insertCoins(Coin::TWENTY_FIVE);

        $stateBefore = $this->currentState();

        try {
            $this->machine->service(['water' => 9], [5 => 9]);
            self::fail('InvalidServiceOperationException was expected');
        } catch (InvalidServiceOperationException) {
            // expected
        }

        self::assertSame($stateBefore, $this->currentState());
    }

    public function testOutOfStockLeavesAllStateUnchanged(): void
    {
        $this->machine->service(['water' => 1, 'juice' => 5, 'soda' => 5], []);
        $this->insertCoins(Coin::TWENTY_FIVE, Coin::TWENTY_FIVE, Coin::TEN, Coin::FIVE);
        $this->machine->selectProduct('water'); // sells the last water

        $fundAfterFirstSale = $this->machine->getCoinInventory();
        $stockAfterFirstSale = $this->machine->getProductStock();
        $this->insertCoins(Coin::TWENTY_FIVE, Coin::TWENTY_FIVE, Coin::TEN, Coin::FIVE); // retry

        try {
            $this->machine->selectProduct('water');
            self::fail('OutOfStockException was expected');
        } catch (OutOfStockException) {
            // expected
        }

        self::assertSame($stockAfterFirstSale, $this->machine->getProductStock());
        self::assertSame($fundAfterFirstSale, $this->machine->getCoinInventory());
        self::assertSame(65, $this->machine->getInsertedTotal()); // failed attempt still refundable
    }

    public function testPurchaseFailsWhenFundCannotProvideExactChange(): void
    {
        // The prompt example: empty fund, customer inserts 1.00 for Water (0.65).
        $this->machine->service(self::DEFAULT_STOCK, []);
        $this->insertCoins(Coin::ONE_HUNDRED);

        $stateBefore = $this->currentState();

        try {
            $this->machine->selectProduct('water');
            self::fail('InsufficientChangeException was expected');
        } catch (InsufficientChangeException) {
            // expected
        }

        self::assertSame($stateBefore, $this->currentState());

        // Nothing was committed, so the customer's money is still theirs.
        self::assertSame([Coin::ONE_HUNDRED], $this->machine->returnCoins());
    }

    public function testInsufficientFundsLeavesAllStateUnchanged(): void
    {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);
        $this->insertCoins(Coin::TWENTY_FIVE, Coin::TWENTY_FIVE); // 50 < juice price

        $stateBefore = $this->currentState();

        try {
            $this->machine->selectProduct('juice');
            self::fail('InsufficientFundsException was expected');
        } catch (InsufficientFundsException) {
            // expected
        }

        self::assertSame($stateBefore, $this->currentState());
    }

    public function testUnknownProductLeavesAllStateUnchanged(): void
    {
        $this->machine->service(self::DEFAULT_STOCK, self::FULL_FUND);
        $this->insertCoins(Coin::ONE_HUNDRED);

        $stateBefore = $this->currentState();

        try {
            $this->machine->selectProduct('cola');
            self::fail('ProductNotFoundException was expected');
        } catch (ProductNotFoundException) {
            // expected
        }

        self::assertSame($stateBefore, $this->currentState());
    }

    private function insertCoins(Coin ...$coins): void
    {
        foreach ($coins as $coin) {
            $this->machine->insertCoin($coin);
        }
    }

    /**
     * Full read-only snapshot of every piece of machine state.
     *
     * @return array{stock: array<string, int>, fund: array<int, int>, inserted: int}
     */
    private function currentState(): array
    {
        return [
            'stock' => $this->machine->getProductStock(),
            'fund' => $this->machine->getCoinInventory(),
            'inserted' => $this->machine->getInsertedTotal(),
        ];
    }
}
