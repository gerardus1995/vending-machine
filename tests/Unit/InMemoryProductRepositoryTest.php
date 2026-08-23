<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\InMemoryProductRepository;
use App\Domain\Money;
use App\Domain\Product;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class InMemoryProductRepositoryTest extends TestCase
{
    public function testFindByIdReturnsProductWhenExists(): void
    {
        $product = new Product('water', 'Water', Money::fromString('0.65'));
        $repo = new InMemoryProductRepository([$product]);

        $found = $repo->findById('water');

        $this->assertInstanceOf(Product::class, $found);
        $this->assertSame('water', $found->id());
        $this->assertSame('Water', $found->name());
        $this->assertEquals(Money::fromString('0.65'), $found->price());
    }

    public function testFindByIdReturnsNullWhenNotExists(): void
    {
        $repo = new InMemoryProductRepository([]);

        $found = $repo->findById('unknown');

        $this->assertNull($found);
    }

    public function testFindAllReturnsAllProducts(): void
    {
        $water = new Product('water', 'Water', Money::fromString('0.65'));
        $juice = new Product('juice', 'Juice', Money::fromString('1.00'));
        $soda = new Product('soda', 'Soda', Money::fromString('1.50'));

        $repo = new InMemoryProductRepository([$water, $juice, $soda]);

        $products = $repo->findAll();

        $this->assertCount(3, $products);
        $this->assertContainsOnlyInstancesOf(Product::class, $products);
        $this->assertContains($water, $products);
        $this->assertContains($juice, $products);
        $this->assertContains($soda, $products);
    }

    public function testCreateDefaultReturnsRepositoryWithThreeProducts(): void
    {
        $repo = InMemoryProductRepository::createDefault();

        $this->assertInstanceOf(InMemoryProductRepository::class, $repo);

        $water = $repo->findById('water');
        $juice = $repo->findById('juice');
        $soda = $repo->findById('soda');

        $this->assertInstanceOf(Product::class, $water);
        $this->assertSame('Water', $water->name());
        $this->assertEquals(Money::fromString('0.65'), $water->price());

        $this->assertInstanceOf(Product::class, $juice);
        $this->assertSame('Juice', $juice->name());
        $this->assertEquals(Money::fromString('1.00'), $juice->price());

        $this->assertInstanceOf(Product::class, $soda);
        $this->assertSame('Soda', $soda->name());
        $this->assertEquals(Money::fromString('1.50'), $soda->price());
    }
}
