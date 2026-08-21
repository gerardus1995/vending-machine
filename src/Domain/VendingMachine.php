<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Coin;
use App\Domain\ChangeCalculatorInterface;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\InvalidServiceOperationException;
use App\Domain\Exception\OutOfStockException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\ProductRepositoryInterface;

/**
 * The VendingMachine aggregate orchestrates the vending machine functionality.
 * It maintains separate inventories for products and change, and tracks the current transaction.
 */
final class VendingMachine
{
    /**
     * @var array<string, int> Mapping of product ID to available quantity
     */
    private array $productStock;

    /**
     * @var array<int, int> Mapping of coin denomination (cents) to available quantity
     * This represents the machine's change fund.
     */
    private array $coinInventory;

    /**
     * @var Coin[] Array of Coin objects inserted by the customer in the current transaction
     */
    private array $insertedCoins = [];

    private ProductRepositoryInterface $productRepository;
    private ChangeCalculatorInterface $changeCalculator;

    /**
     * @param ProductRepositoryInterface $productRepository Repository for product lookup
     * @param ChangeCalculatorInterface $changeCalculator Service for calculating change
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ChangeCalculatorInterface $changeCalculator
    ) {
        $this->productRepository = $productRepository;
        $this->changeCalculator = $changeCalculator;

        // Initialize empty inventories
        $this->productStock = [];
        $this->coinInventory = [];
    }

    /**
     * Insert a coin into the current transaction.
     *
     * @param Coin $coin The coin to insert
     */
    public function insertCoin(Coin $coin): void
    {
        $this->insertedCoins[] = $coin;
    }

    /**
     * Return all coins inserted in the current transaction and clear the transaction.
     *
     * @return Coin[] Array of inserted coins
     */
    public function returnCoins(): array
    {
        $coins = $this->insertedCoins;
        $this->insertedCoins = [];
        return $coins;
    }

    /**
     * Attempt to purchase a product.
     *
     * @param string $productId The ID of the product to purchase
     * @return PurchaseResult The purchased product and change to return
     *
     * @throws ProductNotFoundException   If the product ID is not found
     * @throws OutOfStockException        If the product is out of stock
     * @throws InsufficientFundsException If inserted coins don't cover the product price
     * @throws \DomainException           If exact change cannot be made
     */
    public function selectProduct(string $productId): PurchaseResult
    {
        // 1. Validate that we have a transaction in progress
        if (empty($this->insertedCoins)) {
            throw new InsufficientFundsException('No coins inserted');
        }

        // 2. Find the product
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            throw new ProductNotFoundException(sprintf('Product with ID "%s" not found', $productId));
        }

        // 3. Check stock
        $productIdKey = $product->id();
        if (!isset($this->productStock[$productIdKey]) || $this->productStock[$productIdKey] <= 0) {
            throw new OutOfStockException(sprintf('Product "%s" is out of stock', $product->name()));
        }

        // 4. Calculate total inserted amount
        $insertedTotal = 0;
        foreach ($this->insertedCoins as $coin) {
            $insertedTotal += $coin->toCents();
        }

        // 5. Check if sufficient funds
        $productPrice = $product->price()->cents();
        if ($insertedTotal < $productPrice) {
            throw new InsufficientFundsException(sprintf(
                'Insufficient funds. Inserted: %0.2f, Required: %0.2f',
                $insertedTotal / 100,
                $productPrice / 100
            ));
        }

        // 6. Calculate change needed
        $changeAmount = $insertedTotal - $productPrice;

        // 7. Calculate change using available coins (atomic - validate before mutating state)
        $changeCoins = $this->changeCalculator->calculate($changeAmount, $this->coinInventory);

        // 8. If we reach here, all validations passed. Now mutate state atomically.

        // Decrement product stock
        $this->productStock[$productIdKey]--;

        // Add inserted coins to machine's inventory
        foreach ($this->insertedCoins as $coin) {
            $denom = $coin->toCents();
            $this->coinInventory[$denom] = ($this->coinInventory[$denom] ?? 0) + 1;
        }

        // Subtract change coins from machine's inventory
        foreach ($changeCoins as $denom => $count) {
            $this->coinInventory[$denom] = ($this->coinInventory[$denom] ?? 0) - $count;
            // Ensure we don't go negative (shouldn't happen if calculator worked correctly)
            if ($this->coinInventory[$denom] < 0) {
                $this->coinInventory[$denom] = 0;
            }
        }

        // Clear current transaction
        $this->insertedCoins = [];

        // 9. Return result
        $changeMoney = Money::fromCents($changeAmount);
        return new PurchaseResult($product, $changeMoney);
    }

    /**
     * Reconfigure the machine's product stock and coin inventory.
     * Rejected if there are coins inserted in the current transaction.
     *
     * @param array<string, int> $productStock   Mapping of product ID to quantity
     * @param array<int, int>    $coinInventory  Mapping of coin denomination (cents) to quantity
     *
     * @throws InvalidServiceOperationException If coins are inserted in current transaction
     */
    public function service(array $productStock, array $coinInventory): void
    {
        if (!empty($this->insertedCoins)) {
            throw new InvalidServiceOperationException(
                'Cannot service machine while coins are inserted in current transaction'
            );
        }

        $this->productStock = $productStock;
        $this->coinInventory = $coinInventory;
    }

    /**
     * Get the current product stock (for testing/inspection).
     *
     * @return array<string, int> Mapping of product ID to quantity
     */
    public function getProductStock(): array
    {
        return $this->productStock;
    }

    /**
     * Get the current coin inventory (for testing/inspection).
     *
     * @return array<int, int> Mapping of coin denomination to quantity
     */
    public function getCoinInventory(): array
    {
        return $this->coinInventory;
    }

    /**
     * Get the total value of inserted coins in the current transaction.
     *
     * @return int Total cents inserted
     */
    public function getInsertedTotal(): int
    {
        $total = 0;
        foreach ($this->insertedCoins as $coin) {
            $total += $coin->toCents();
        }
        return $total;
    }
}