<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Exception\InvalidMoneyException;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MoneyTest extends TestCase
{
    public function testCanBeCreatedFromCents(): void
    {
        $money = Money::fromCents(65);
        $this->assertSame(65, $money->cents());
    }

    public function testCanBeCreatedFromString(): void
    {
        $money = Money::fromString('0.65');
        $this->assertSame(65, $money->cents());
    }

    public function testCanBeCreatedFromStringWithSpecificExamples(): void
    {
        $this->assertSame(5, Money::fromString('0.05')->cents());
        $this->assertSame(10, Money::fromString('0.10')->cents());
        $this->assertSame(25, Money::fromString('0.25')->cents());
        $this->assertSame(100, Money::fromString('1')->cents());
        $this->assertSame(100, Money::fromString('1.00')->cents());
        $this->assertSame(150, Money::fromString('1.5')->cents());
    }

    public function testRejectsNegativeCentsInConstructor(): void
    {
        $this->expectException(InvalidMoneyException::class);
        new Money(-5);
    }

    public function testRejectsNegativeCentsFromString(): void
    {
        $this->expectException(InvalidMoneyException::class);
        Money::fromString('-0.05');
    }

    public function testRejectsInvalidStringFormat(): void
    {
        $this->expectException(InvalidMoneyException::class);
        Money::fromString('abc');
    }

    public function testRejectsStringWithMoreThanTwoDecimals(): void
    {
        $this->expectException(InvalidMoneyException::class);
        Money::fromString('0.123');
    }

    public function testImmutability(): void
    {
        $money = Money::fromCents(50);
        $added = $money->add(Money::fromCents(25));
        $this->assertSame(50, $money->cents(), 'Original money unchanged after addition');
        $this->assertSame(75, $added->cents());
    }

    public function testAddition(): void
    {
        $money = Money::fromCents(25);
        $other = Money::fromCents(10);
        $sum = $money->add($other);
        $this->assertSame(35, $sum->cents());
    }

    public function testSubtraction(): void
    {
        $money = Money::fromCents(50);
        $other = Money::fromCents(20);
        $difference = $money->subtract($other);
        $this->assertSame(30, $difference->cents());
    }

    public function testSubtractionThatWouldResultInNegativeThrowsException(): void
    {
        $this->expectException(InvalidMoneyException::class);
        Money::fromCents(10)->subtract(Money::fromCents(20));
    }

    public function testEquality(): void
    {
        $money1 = Money::fromCents(100);
        $money2 = Money::fromCents(100);
        $money3 = Money::fromCents(50);
        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
    }

    public function testGreaterThan(): void
    {
        $money1 = Money::fromCents(150);
        $money2 = Money::fromCents(100);
        $this->assertTrue($money1->greaterThan($money2));
        $this->assertFalse($money2->greaterThan($money1));
    }

    public function testGreaterThanOrEqual(): void
    {
        $money1 = Money::fromCents(100);
        $money2 = Money::fromCents(100);
        $money3 = Money::fromCents(50);
        $this->assertTrue($money1->greaterThanOrEqual($money2));
        $this->assertTrue($money1->greaterThanOrEqual($money3));
        $this->assertFalse($money3->greaterThanOrEqual($money1));
    }

    public function testIsZero(): void
    {
        $zero = Money::fromCents(0);
        $positive = Money::fromCents(5);
        $this->assertTrue($zero->isZero());
        $this->assertFalse($positive->isZero());
    }

    public function testStringRepresentation(): void
    {
        $this->assertSame('0.05', (string) Money::fromCents(5));
        $this->assertSame('0.10', (string) Money::fromCents(10));
        $this->assertSame('0.25', (string) Money::fromCents(25));
        $this->assertSame('1.00', (string) Money::fromCents(100));
        $this->assertSame('0.65', (string) Money::fromCents(65));
        $this->assertSame('1.50', (string) Money::fromCents(150));
        // Ensure two decimal places
        $this->assertSame('0.50', (string) Money::fromCents(50));
        $this->assertSame('0.00', (string) Money::fromCents(0));
    }
}
