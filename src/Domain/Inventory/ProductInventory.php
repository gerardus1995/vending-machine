<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Exception\ProductNotFoundException;

/**
 * The machine's product stock.
 *
 * Domain invariants:
 * - only products known to the machine's catalogue can be stored;
 * - every stored quantity is >= 0 - an explicit "0" is kept as "sold out",
 *   which preserves the distinction between a product not configured (absent)
 *   and one that is known but temporarily out of stock (0);
 * - units can never be removed below zero.
 */
final class ProductInventory
{
    /** @var array<string, int> product id => quantity */
    private array $quantities;

    /**
     * @param array<int, string> $knownProductIds   product ids the machine's catalogue recognises
     * @param array<string, int> $initialQuantities product id => quantity
     *
     * @throws ProductNotFoundException  when a product id is not part of the catalogue
     * @throws \InvalidArgumentException when a quantity is negative
     */
    public function __construct(
        private readonly array $knownProductIds,
        array $initialQuantities = [],
    ) {
        $this->quantities = [];
        foreach ($initialQuantities as $productId => $quantity) {
            $this->assertValidEntry($productId, $quantity);
            $this->quantities[$productId] = $quantity;
        }
    }

    public function addUnits(string $productId, int $count = 1): void
    {
        self::assertPositiveCount($count);
        $this->assertKnownProduct($productId);

        $this->quantities[$productId] = ($this->quantities[$productId] ?? 0) + $count;
    }

    /**
     * @throws \DomainException when fewer than $count units are available
     */
    public function removeUnits(string $productId, int $count = 1): void
    {
        self::assertPositiveCount($count);
        $this->assertKnownProduct($productId);

        $available = $this->quantities[$productId] ?? 0;

        if ($available < $count) {
            throw new \DomainException(sprintf(
                'Cannot remove %d unit(s) of "%s": only %d available',
                $count,
                $productId,
                $available
            ));
        }

        $this->quantities[$productId] = $available - $count;
    }

    /**
     * Replaces the whole stock with a new configuration (SERVICE operation).
     * Validates the complete configuration before applying it, so a failure
     * leaves the current stock untouched.
     *
     * @param array<string, int> $quantities product id => quantity
     *
     * @throws ProductNotFoundException  when a product id is not part of the catalogue
     * @throws \InvalidArgumentException when a quantity is negative
     */
    public function configure(array $quantities): void
    {
        $validatedQuantities = [];
        foreach ($quantities as $productId => $quantity) {
            $this->assertValidEntry($productId, $quantity);
            $validatedQuantities[$productId] = $quantity;
        }

        $this->quantities = $validatedQuantities;
    }

    /**
     * Read-only snapshot of the stock: product id => quantity.
     * Callers receive a copy and cannot mutate the inventory through it.
     *
     * @return array<string, int>
     */
    public function quantities(): array
    {
        return $this->quantities;
    }

    public function quantityOf(string $productId): int
    {
        return $this->quantities[$productId] ?? 0;
    }

    private function assertKnownProduct(string $productId): void
    {
        if (!\in_array($productId, $this->knownProductIds, true)) {
            throw new ProductNotFoundException(sprintf(
                'Unknown product "%s"',
                $productId
            ));
        }
    }

    private function assertValidEntry(string $productId, int $quantity): void
    {
        if (!\in_array($productId, $this->knownProductIds, true)) {
            throw new ProductNotFoundException(sprintf(
                'Unknown product "%s"',
                $productId
            ));
        }

        if ($quantity < 0) {
            throw new \InvalidArgumentException(sprintf(
                'Quantity for product "%s" cannot be negative',
                $productId
            ));
        }
    }

    private static function assertPositiveCount(int $count): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Unit count must be at least 1');
        }
    }
}
