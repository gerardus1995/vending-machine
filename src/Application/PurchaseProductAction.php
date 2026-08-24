<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exception\InsufficientChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\OutOfStockException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\Result\PurchaseResult;
use App\Domain\VendingMachine;

/**
 * Purchases the selected product with the currently inserted coins.
 */
final class PurchaseProductAction
{
    public function __construct(private readonly VendingMachine $vendingMachine) {}

    /**
     * Domain exceptions are not translated here: presenting failures is the
     * responsibility of the interface layer on top of this action.
     *
     * @throws ProductNotFoundException    when the product does not exist
     * @throws OutOfStockException         when the product has no stock left
     * @throws InsufficientFundsException  when the inserted money does not cover the price
     * @throws InsufficientChangeException when the machine cannot provide exact change
     */
    public function execute(string $productId): PurchaseResult
    {
        return $this->vendingMachine->selectProduct($productId);
    }
}
