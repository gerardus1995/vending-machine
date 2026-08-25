<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Catalogue;

use App\Domain\Catalogue\ProductCatalogue;
use App\Domain\Entity\Product;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProductCatalogueTest extends TestCase
{
    public function testRejectsAnEmptyCatalogue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProductCatalogue([]);
    }

    public function testGetReturnsTheRequestedProduct(): void
    {
        $catalogue = $this->buildCatalogue();

        self::assertSame('Water', $catalogue->get('water')->name());
        self::assertSame(65, $catalogue->get('water')->price()->cents());
    }

    public function testGetThrowsForUnknownProducts(): void
    {
        $catalogue = $this->buildCatalogue();

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product "tea" does not exist');

        $catalogue->get('tea');
    }

    public function testHasKnowsWhichSelectorsExist(): void
    {
        $catalogue = $this->buildCatalogue();

        self::assertTrue($catalogue->has('juice'));
        self::assertFalse($catalogue->has('tea'));
    }

    public function testKnownIdsListsEverySelector(): void
    {
        $catalogue = $this->buildCatalogue();

        self::assertSame(['water', 'juice', 'soda'], $catalogue->knownIds());
    }

    public function testRejectsAMapKeyThatDoesNotMatchItsProductId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Catalogue key "soda" does not match product id "water"');

        new ProductCatalogue([
            'soda' => new Product('water', 'Water', Money::fromCents(65)),
        ]);
    }

    private function buildCatalogue(): ProductCatalogue
    {
        return new ProductCatalogue([
            'water' => new Product('water', 'Water', Money::fromCents(65)),
            'juice' => new Product('juice', 'Juice', Money::fromCents(100)),
            'soda' => new Product('soda', 'Soda', Money::fromCents(150)),
        ]);
    }
}
