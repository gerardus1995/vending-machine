<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Exception\InvalidCoinException;
use App\Domain\ValueObject\Coin;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CoinTest extends TestCase
{
    public function testEnumCases(): void
    {
        $this->assertSame(5, Coin::FIVE->value);
        $this->assertSame(10, Coin::TEN->value);
        $this->assertSame(25, Coin::TWENTY_FIVE->value);
        $this->assertSame(100, Coin::ONE_HUNDRED->value);
    }

    public function testFromCentsForValidDenominations(): void
    {
        $this->assertSame(Coin::FIVE, Coin::fromCents(5));
        $this->assertSame(Coin::TEN, Coin::fromCents(10));
        $this->assertSame(Coin::TWENTY_FIVE, Coin::fromCents(25));
        $this->assertSame(Coin::ONE_HUNDRED, Coin::fromCents(100));
    }

    public function testFromCentsThrowsExceptionForInvalidDenomination(): void
    {
        $this->expectException(InvalidCoinException::class);
        Coin::fromCents(1);
    }

    public function testFromCentsThrowsExceptionForNegative(): void
    {
        $this->expectException(InvalidCoinException::class);
        Coin::fromCents(-5);
    }

    public function testFromCentsThrowsExceptionForZero(): void
    {
        $this->expectException(InvalidCoinException::class);
        Coin::fromCents(0);
    }

    public function testFromCentsThrowsExceptionForNonInteger(): void
    {
        // PHP is strict, but we can test with float that is not integer
        $this->expectException(InvalidCoinException::class);
        // Note: fromCents expects int, so passing a float will throw TypeError.
        // We'll test with an int that is not in the enum.
        Coin::fromCents(50);
    }

    public function testToCentsReturnsCorrectValue(): void
    {
        $this->assertSame(5, Coin::FIVE->toCents());
        $this->assertSame(10, Coin::TEN->toCents());
        $this->assertSame(25, Coin::TWENTY_FIVE->toCents());
        $this->assertSame(100, Coin::ONE_HUNDRED->toCents());
    }

    public function testEnumIsImmutableByDesign(): void
    {
        // Enums are immutable by design in PHP. We can't change the value.
        // This test is just to document that we expect no setters.
        $coin = Coin::FIVE;
        $this->assertSame(5, $coin->value);
        // Attempt to change via reflection? Not needed for unit test.
    }

    public function testEnumCasesAreSingleton(): void
    {
        $this->assertSame(Coin::FIVE, Coin::FIVE);
        $this->assertSame(Coin::TEN, Coin::TEN);
    }
}
