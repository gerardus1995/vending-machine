<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\ChangeCalculatorInterface;
use App\Domain\GreedyChangeCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class GreedyChangeCalculatorTest extends TestCase
{
    private ChangeCalculatorInterface $calculator;

    protected function setUp(): void
    {
        $this->calculator = new GreedyChangeCalculator();
    }

    public function testReturnsNoChangeForZeroAmount(): void
    {
        $available = [5 => 10, 10 => 10, 25 => 10, 100 => 10];
        $change = $this->calculator->calculate(0, $available);
        $this->assertEquals([], $change);
    }

    public function testReturnsExactChangeWithSingleDenomination(): void
    {
        $available = [5 => 10, 10 => 10, 25 => 10, 100 => 10];
        $change = $this->calculator->calculate(25, $available);
        $this->assertEquals([25 => 1], $change);
    }

    public function testReturnsChangeWithMultipleDenominations(): void
    {
        $available = [5 => 10, 10 => 10, 25 => 10, 100 => 10];
        $change = $this->calculator->calculate(65, $available);
        // Expected: 25*2 + 10*1 + 5*1 = 65
        $this->assertEquals([25 => 2, 10 => 1, 5 => 1], $change);
    }

    public function testRespectsAvailableCoinLimits(): void
    {
        // Only one quarter available
        $available = [5 => 10, 10 => 10, 25 => 1, 100 => 10];
        $change = $this->calculator->calculate(65, $available);
        // Should use one quarter, then need 40 cents: four dimes
        $this->assertEquals([25 => 1, 10 => 4], $change);
    }

    public function testThrowsExceptionWhenChangeCannotBeMade(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot make exact change with available coins');

        // No nickels, need to make 15 cents with only dimes and quarters -> impossible
        $available = [5 => 0, 10 => 1, 25 => 1, 100 => 10];
        $this->calculator->calculate(15, $available);
    }

    public function testWorksWithOnlyNickels(): void
    {
        $available = [5 => 20, 10 => 0, 25 => 0, 100 => 0];
        $change = $this->calculator->calculate(30, $available);
        $this->assertEquals([5 => 6], $change);
    }

    public function testPrefersLargerDenominations(): void
    {
        $available = [5 => 10, 10 => 10, 25 => 10, 100 => 10];
        $change = $this->calculator->calculate(100, $available);
        // Should use one dollar coin, not four quarters or ten dimes, etc.
        $this->assertEquals([100 => 1], $change);
    }

    public function testHandlesMissingDenominationsInAvailableArray(): void
    {
        // Available array might not have all denominations; missing keys treated as zero
        $available = [5 => 10, 25 => 10]; // no dimes or dollars
        $change = $this->calculator->calculate(30, $available);
        // Should use one quarter and one nickel
        $this->assertEquals([25 => 1, 5 => 1], $change);
    }

    public function testThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount to change cannot be negative');

        $available = [5 => 10, 10 => 10, 25 => 10, 100 => 10];
        $this->calculator->calculate(-5, $available);
    }
}
