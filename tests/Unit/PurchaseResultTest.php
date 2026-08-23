<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Money;
use App\Domain\Product;
use App\Domain\PurchaseResult;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PurchaseResultTest extends TestCase
{
    public function testCanBeCreatedWithProductAndChange(): void
    {
        $product = new Product('water', 'Water', Money::fromString('0.65'));
        $change = Money::fromCents(35);
        $result = new PurchaseResult($product, $change);

        $this->assertSame($product, $result->product());
        $this->assertSame($change, $result->change());
    }

    public function testIsImmutable(): void
    {
        $product = new Product('water', 'Water', Money::fromString('0.65'));
        $change = Money::fromCents(35);
        $result = new PurchaseResult($product, $change);

        // We cannot change the properties after construction (no setters).
        // This test just ensures the getters return the same objects.
        $this->assertSame($product, $result->product());
        $this->assertSame($change, $result->change());
    }
}
