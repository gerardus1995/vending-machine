<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Inventory;

use App\Domain\Exception\ProductNotFoundException;
use App\Domain\Inventory\ProductInventory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProductInventoryTest extends TestCase
{
    private const KNOWN = ['water', 'juice', 'soda'];

    public function testStartsEmptyByDefault(): void
    {
        $inventory = new ProductInventory(self::KNOWN);

        self::assertSame([], $inventory->quantities());
        self::assertSame(0, $inventory->quantityOf('water'));
    }

    public function testAcceptsKnownProductsWithPositiveQuantities(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 3, 'juice' => 1]);

        self::assertSame(3, $inventory->quantityOf('water'));
        self::assertSame(1, $inventory->quantityOf('juice'));
        self::assertSame(0, $inventory->quantityOf('soda'));
    }

    public function testZeroQuantitiesAreKeptAsSoldOut(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 0]);

        self::assertSame(['water' => 0], $inventory->quantities());
        self::assertSame(0, $inventory->quantityOf('water'));
    }

    public function testRejectsUnknownProduct(): void
    {
        $this->expectException(ProductNotFoundException::class);

        new ProductInventory(self::KNOWN, ['cola' => 5]);
    }

    public function testRejectsNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProductInventory(self::KNOWN, ['water' => -1]);
    }

    public function testAddUnitsAccumulatesPerProduct(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 2]);

        $inventory->addUnits('water');
        $inventory->addUnits('soda', 3);

        self::assertSame(3, $inventory->quantityOf('water'));
        self::assertSame(3, $inventory->quantityOf('soda'));
    }

    public function testAddUnitsRejectsUnknownProduct(): void
    {
        $inventory = new ProductInventory(self::KNOWN);

        $this->expectException(ProductNotFoundException::class);

        $inventory->addUnits('cola');
    }

    public function testAddUnitsDefaultsToASingleUnit(): void
    {
        $inventory = new ProductInventory(self::KNOWN);
        $inventory->addUnits('juice');

        self::assertSame(1, $inventory->quantityOf('juice'));
    }

    public function testRemoveUnitsDecreasesTheQuantity(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 4]);

        $inventory->removeUnits('water', 3);

        self::assertSame(1, $inventory->quantityOf('water'));
    }

    public function testRemovingTheLastUnitLeavesZeroInStock(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 1]);

        $inventory->removeUnits('water');

        self::assertSame(['water' => 0], $inventory->quantities());
    }

    public function testCannotRemoveMoreUnitsThanAvailable(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 2]);

        try {
            $inventory->removeUnits('water', 3);
            self::fail('DomainException was expected');
        } catch (\DomainException $exception) {
            self::assertNotEmpty($exception->getMessage());
        }

        self::assertSame(2, $inventory->quantityOf('water'));
    }

    public function testConfigureReplacesTheWholeStock(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 10, 'soda' => 5]);

        $inventory->configure(['juice' => 4]);

        self::assertSame(['juice' => 4], $inventory->quantities());
    }

    public function testFailedConfigureLeavesTheStockUntouched(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 3]);

        try {
            $inventory->configure(['cola' => 1]);
            self::fail('ProductNotFoundException was expected');
        } catch (ProductNotFoundException) {
            // expected
        }

        self::assertSame(['water' => 3], $inventory->quantities());
    }

    public function testQuantitiesSnapshotCannotMutateTheInventory(): void
    {
        $inventory = new ProductInventory(self::KNOWN, ['water' => 4]);

        $snapshot = $inventory->quantities();
        $snapshot['water'] = 99;

        self::assertSame(4, $inventory->quantityOf('water'));
    }
}
