<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Represents the outcome of a successful product purchase.
 */
final class PurchaseResult
{
    private readonly Product $product;
    private readonly Money $change;

    public function __construct(Product $product, Money $change)
    {
        $this->product = $product;
        $this->change = $change;
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function change(): Money
    {
        return $this->change;
    }
}
