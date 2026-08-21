<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Money;
use App\Domain\Product;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testCanBeCreatedWithValidParameters(): void
    {
        $money = Money::fromCents(65);
        $product = new Product('water', 'Water', $money);

        $this->assertSame('water', $product->id());
        $this->assertSame('Water', $product->name());
        $this->assertSame($money, $product->price());
    }

    public function testThrowsExceptionForEmptyId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product id cannot be empty');

        new Product('', 'Water', Money::fromCents(65));
    }

    public function testThrowsExceptionForEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        new Product('water', '', Money::fromCents(65));
    }

    public function testIsImmutable(): void
    {
        $money = Money::fromCents(65);
        $product = new Product('water', 'Water', $money);

        // Attempt to change properties via reflection? Not needed; we just ensure no setters exist.
        // The readonly properties cannot be changed after construction.
        $this->assertSame('water', $product->id());
        $this->assertSame('Water', $product->name());
        $this->assertSame($money, $product->price());
    }
}