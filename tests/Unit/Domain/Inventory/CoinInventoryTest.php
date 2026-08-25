<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Inventory;

use App\Domain\Exception\InvalidCoinException;
use App\Domain\Inventory\CoinInventory;
use App\Domain\ValueObject\Coin;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CoinInventoryTest extends TestCase
{
    public function testStartsEmptyByDefault(): void
    {
        $inventory = new CoinInventory();

        self::assertSame([], $inventory->quantities());
        self::assertSame(0, $inventory->quantityOf(Coin::TEN));
    }

    public function testAcceptsValidDenominationsWithPositiveQuantities(): void
    {
        $inventory = new CoinInventory([5 => 3, 10 => 2, 25 => 1, 100 => 4]);

        self::assertSame(3, $inventory->quantityOf(Coin::FIVE));
        self::assertSame(2, $inventory->quantityOf(Coin::TEN));
        self::assertSame(1, $inventory->quantityOf(Coin::TWENTY_FIVE));
        self::assertSame(4, $inventory->quantityOf(Coin::ONE_HUNDRED));
    }

    public function testZeroQuantitiesAreNotStored(): void
    {
        $inventory = new CoinInventory([25 => 0]);

        self::assertSame([], $inventory->quantities());
    }

    public function testRejectsInvalidDenomination(): void
    {
        $this->expectException(InvalidCoinException::class);

        new CoinInventory([30 => 5]);
    }

    public function testRejectsNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CoinInventory([25 => -1]);
    }

    public function testAddCoinsAccumulatesPerDenomination(): void
    {
        $inventory = new CoinInventory([25 => 2]);

        $inventory->addCoins(Coin::TWENTY_FIVE);
        $inventory->addCoins(Coin::TEN, 3);

        self::assertSame(3, $inventory->quantityOf(Coin::TWENTY_FIVE));
        self::assertSame(3, $inventory->quantityOf(Coin::TEN));
    }

    public function testAddCoinsRejectsNonPositiveCounts(): void
    {
        $inventory = new CoinInventory();

        $this->expectException(\InvalidArgumentException::class);

        $inventory->addCoins(Coin::FIVE, 0);
    }

    public function testRemoveCoinsDecreasesTheQuantity(): void
    {
        $inventory = new CoinInventory([10 => 4]);

        $inventory->removeCoins(Coin::TEN, 3);

        self::assertSame(1, $inventory->quantityOf(Coin::TEN));
    }

    public function testRemovingTheLastCoinDropsTheEntry(): void
    {
        $inventory = new CoinInventory([10 => 1]);

        $inventory->removeCoins(Coin::TEN);

        self::assertSame([], $inventory->quantities());
    }

    public function testCannotRemoveMoreCoinsThanAvailable(): void
    {
        $inventory = new CoinInventory([25 => 2]);

        try {
            $inventory->removeCoins(Coin::TWENTY_FIVE, 3);
            self::fail('DomainException was expected');
        } catch (\DomainException $exception) {
            self::assertNotEmpty($exception->getMessage());
        }

        self::assertSame(2, $inventory->quantityOf(Coin::TWENTY_FIVE));
    }

    public function testQuantitiesSnapshotCannotMutateTheInventory(): void
    {
        $inventory = new CoinInventory([25 => 4]);

        $snapshot = $inventory->quantities();
        $snapshot[25] = 99;

        self::assertSame(4, $inventory->quantityOf(Coin::TWENTY_FIVE));
    }
}
