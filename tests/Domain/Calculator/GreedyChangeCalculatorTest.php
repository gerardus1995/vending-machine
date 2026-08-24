<?php

declare(strict_types=1);

namespace App\Tests\Domain\Calculator;

use App\Domain\Calculator\GreedyChangeCalculator;
use App\Domain\Exception\InsufficientChangeException;
use App\Domain\ValueObject\Coin;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class GreedyChangeCalculatorTest extends TestCase
{
    private GreedyChangeCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new GreedyChangeCalculator();
    }

    public function testReturnsNoCoinsForZeroAmount(): void
    {
        self::assertSame([], $this->calculator->calculate(0, [5 => 10, 10 => 10, 25 => 10, 100 => 10]));
    }

    public function testUsesASingleCoinWhenPossible(): void
    {
        $change = $this->calculator->calculate(25, [5 => 10, 10 => 10, 25 => 10, 100 => 10]);

        self::assertSame([Coin::TWENTY_FIVE], $change);
    }

    public function testCombinesDenominationsForComplexAmounts(): void
    {
        $change = $this->calculator->calculate(65, [5 => 10, 10 => 10, 25 => 10, 100 => 10]);

        self::assertSame(
            [Coin::TWENTY_FIVE, Coin::TWENTY_FIVE, Coin::TEN, Coin::FIVE],
            $change
        );
    }

    public function testRespectsAvailableQuantities(): void
    {
        // Only one quarter available: fall back to dimes for the rest.
        $change = $this->calculator->calculate(65, [5 => 10, 10 => 10, 25 => 1, 100 => 10]);

        self::assertSame(
            [Coin::TWENTY_FIVE, Coin::TEN, Coin::TEN, Coin::TEN, Coin::TEN],
            $change
        );
    }

    public function testPrefersTheLargestDenomination(): void
    {
        $change = $this->calculator->calculate(100, [5 => 10, 10 => 10, 25 => 10, 100 => 10]);

        self::assertSame([Coin::ONE_HUNDRED], $change);
    }

    public function testMissingDenominationsAreTreatedAsUnavailable(): void
    {
        $change = $this->calculator->calculate(30, [5 => 10, 25 => 10]);

        self::assertSame([Coin::TWENTY_FIVE, Coin::FIVE], $change);
    }

    public function testWorksWithANickelOnlyFund(): void
    {
        $change = $this->calculator->calculate(30, [5 => 20]);

        self::assertSame(
            [Coin::FIVE, Coin::FIVE, Coin::FIVE, Coin::FIVE, Coin::FIVE, Coin::FIVE],
            $change
        );
    }

    public function testThrowsWhenExactChangeIsImpossible(): void
    {
        // 15 cents cannot be made from one dime and one quarter.
        $this->expectException(InsufficientChangeException::class);

        $this->calculator->calculate(15, [10 => 1, 25 => 1]);
    }

    public function testDoesNotMutateTheAvailabilityInput(): void
    {
        $availableCoins = [5 => 10, 10 => 10, 25 => 10];

        $this->calculator->calculate(35, $availableCoins);

        self::assertSame([5 => 10, 10 => 10, 25 => 10], $availableCoins);
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate(-5, [5 => 10]);
    }
}
