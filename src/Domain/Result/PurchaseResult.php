<?php

declare(strict_types=1);

namespace App\Domain\Result;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;

/**
 * Outcome of a successful purchase: the dispensed product and the actual
 * coins returned as change.
 */
final class PurchaseResult
{
    /**
     * @param list<Coin> $changeCoins
     */
    public function __construct(
        private readonly Product $product,
        private readonly array $changeCoins,
    ) {}

    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * @return list<Coin>
     */
    public function getChangeCoins(): array
    {
        return $this->changeCoins;
    }

    /**
     * Derived convenience value: the total amount returned as change.
     */
    public function getChangeTotal(): Money
    {
        $totalCents = 0;
        foreach ($this->changeCoins as $coin) {
            $totalCents += $coin->toCents();
        }

        return Money::fromCents($totalCents);
    }
}
