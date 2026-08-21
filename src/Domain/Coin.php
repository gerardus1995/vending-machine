<?php

declare(strict_types=1);

namespace App\Domain;

enum Coin: int
{
    case FIVE = 5;
    case TEN = 10;
    case TWENTY_FIVE = 25;
    case ONE_HUNDRED = 100;

    /**
     * Create a Coin from a cents value.
     *
     * @param int $cents The coin value in cents.
     * @return self
     * @throws InvalidCoinException
     */
    public static function fromCents(int $cents): self
    {
        return self::tryFrom($cents)
            ?? throw new InvalidCoinException(sprintf('Unsupported coin denomination: %d cents', $cents));
    }

    /**
     * Get the value in cents.
     *
     * @return int
     */
    public function toCents(): int
    {
        return $this->value;
    }
}