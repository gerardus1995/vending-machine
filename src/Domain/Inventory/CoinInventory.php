<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Exception\InvalidCoinException;
use App\Domain\ValueObject\Coin;

/**
 * The machine's change fund.
 *
 * Domain invariants:
 * - only valid Coin denominations are stored;
 * - every stored quantity is >= 0 (zero-quantity entries are dropped);
 * - coins can never be removed beyond the available quantity.
 */
final class CoinInventory
{
    /** @var array<int, int> denomination (cents) => quantity */
    private array $quantities;

    /**
     * @param array<int, int> $initialQuantities denomination (cents) => quantity
     *
     * @throws InvalidCoinException      when a denomination is not an accepted coin
     * @throws \InvalidArgumentException when a quantity is negative
     */
    public function __construct(array $initialQuantities = [])
    {
        $this->quantities = [];
        foreach ($initialQuantities as $denomination => $quantity) {
            self::assertValidEntry($denomination, $quantity);

            if ($quantity > 0) {
                $this->quantities[$denomination] = $quantity;
            }
        }
    }

    public function addCoins(Coin $coin, int $count = 1): void
    {
        self::assertPositiveCount($count);

        $denomination = $coin->toCents();
        $this->quantities[$denomination] = ($this->quantities[$denomination] ?? 0) + $count;
    }

    /**
     * @throws \DomainException when fewer than $count coins are available
     */
    public function removeCoins(Coin $coin, int $count = 1): void
    {
        self::assertPositiveCount($count);

        $denomination = $coin->toCents();
        $available = $this->quantities[$denomination] ?? 0;

        if ($available < $count) {
            throw new \DomainException(sprintf(
                'Cannot remove %d coin(s) of %d cents: only %d available',
                $count,
                $denomination,
                $available
            ));
        }

        if ($available === $count) {
            unset($this->quantities[$denomination]);
        } else {
            $this->quantities[$denomination] = $available - $count;
        }
    }

    /**
     * Read-only snapshot of the fund: denomination (cents) => quantity.
     * Callers receive a copy and cannot mutate the inventory through it.
     *
     * @return array<int, int>
     */
    public function quantities(): array
    {
        return $this->quantities;
    }

    public function quantityOf(Coin $coin): int
    {
        return $this->quantities[$coin->toCents()] ?? 0;
    }

    private static function assertValidEntry(int $denomination, int $quantity): void
    {
        if (null === Coin::tryFrom($denomination)) {
            throw new InvalidCoinException(sprintf(
                '%d cents is not an accepted coin denomination',
                $denomination
            ));
        }

        if ($quantity < 0) {
            throw new \InvalidArgumentException(sprintf(
                'Quantity for %d cent coins cannot be negative',
                $denomination
            ));
        }
    }

    private static function assertPositiveCount(int $count): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Coin count must be at least 1');
        }
    }
}
