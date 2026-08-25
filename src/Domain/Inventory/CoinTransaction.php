<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;

/**
 * The customer's current transaction: the coins inserted so far, held apart
 * from the machine's change fund until a purchase succeeds or they are
 * returned.
 *
 * Domain invariants:
 * - the amount is always derived from the actual coins held, never tracked
 *   separately, so it cannot drift;
 * - draining hands back every coin exactly once and empties the transaction.
 */
final class CoinTransaction
{
    /** @var list<Coin> coins in insertion order */
    private array $coins = [];

    public function insert(Coin $coin): void
    {
        $this->coins[] = $coin;
    }

    /**
     * Total value of the inserted coins.
     */
    public function amount(): Money
    {
        $totalCents = 0;
        foreach ($this->coins as $coin) {
            $totalCents += $coin->toCents();
        }

        return Money::fromCents($totalCents);
    }

    public function isEmpty(): bool
    {
        return [] === $this->coins;
    }

    /**
     * Returns every inserted coin in insertion order and clears the transaction.
     *
     * @return list<Coin>
     */
    public function drain(): array
    {
        $drainedCoins = $this->coins;
        $this->coins = [];

        return $drainedCoins;
    }
}
