<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Transaction;

use App\Domain\Transaction\CoinTransaction;
use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CoinTransactionTest extends TestCase
{
    public function testStartsEmptyWithZeroAmount(): void
    {
        $transaction = new CoinTransaction();

        self::assertTrue($transaction->isEmpty());
        self::assertTrue($transaction->amount()->isZero());
    }

    public function testInsertedCoinsAccumulateIntoTheAmount(): void
    {
        $transaction = new CoinTransaction();

        $transaction->insert(Coin::ONE_HUNDRED);
        $transaction->insert(Coin::TWENTY_FIVE);
        $transaction->insert(Coin::TEN);
        $transaction->insert(Coin::FIVE);

        self::assertFalse($transaction->isEmpty());
        self::assertSame(140, $transaction->amount()->cents());
    }

    public function testAmountIsAlwaysDerivedFromTheActualCoins(): void
    {
        $transaction = new CoinTransaction();
        $transaction->insert(Coin::TWENTY_FIVE);
        $transaction->drain();

        self::assertTrue($transaction->amount()->isZero());
    }

    public function testDrainReturnsEveryCoinInInsertionOrder(): void
    {
        $transaction = new CoinTransaction();
        $transaction->insert(Coin::ONE_HUNDRED);
        $transaction->insert(Coin::TWENTY_FIVE);
        $transaction->insert(Coin::FIVE);

        self::assertSame(
            [Coin::ONE_HUNDRED, Coin::TWENTY_FIVE, Coin::FIVE],
            $transaction->drain()
        );
    }

    public function testDrainClearsTheTransaction(): void
    {
        $transaction = new CoinTransaction();
        $transaction->insert(Coin::TEN);

        self::assertNotEmpty($transaction->drain());

        self::assertTrue($transaction->isEmpty());
        self::assertSame([], $transaction->drain()); // second drain hands back nothing
    }

    public function testAcceptsNewCoinsAfterBeingDrained(): void
    {
        $transaction = new CoinTransaction();
        $transaction->insert(Coin::TEN);
        $transaction->drain();

        $transaction->insert(Coin::FIVE);

        self::assertSame(5, $transaction->amount()->cents());
    }

    public function testKeepsDuplicateCoinsOfTheSameDenomination(): void
    {
        $transaction = new CoinTransaction();

        $transaction->insert(Coin::TWENTY_FIVE);
        $transaction->insert(Coin::TWENTY_FIVE);
        $transaction->insert(Coin::TWENTY_FIVE);

        self::assertSame(75, $transaction->amount()->cents());
        self::assertCount(3, $transaction->drain());
    }

    public function testAmountIsAMoneyValueObject(): void
    {
        $transaction = new CoinTransaction();
        $transaction->insert(Coin::TWENTY_FIVE);
        $transaction->insert(Coin::TEN);

        self::assertInstanceOf(Money::class, $transaction->amount());
        self::assertSame('0.35', (string) $transaction->amount());
    }
}
