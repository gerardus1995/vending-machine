<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Calculator\GreedyChangeCalculator;
use App\Domain\Entity\Product;
use App\Domain\Exception\InsufficientChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\InvalidCoinException;
use App\Domain\Exception\InvalidServiceOperationException;
use App\Domain\Exception\OutOfStockException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\Inventory\CoinInventory;
use App\Domain\Result\PurchaseResult;
use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;

/**
 * Aggregate root of the vending machine.
 *
 * Owns the product catalogue and stock, the change fund (CoinInventory) and the
 * current customer transaction. All business operations are atomic: when any
 * validation fails, no state is modified.
 */
final class VendingMachine
{
    /** @var array<string, Product> keyed by product id */
    private array $products;

    /** @var array<string, int> product id => units in stock */
    private array $productStock;

    private CoinInventory $coinInventory;

    /** @var list<Coin> coins inserted by the customer in the current transaction */
    private array $insertedCoins = [];

    private readonly GreedyChangeCalculator $changeCalculator;

    public function __construct()
    {
        $this->products = [
            'water' => new Product('water', 'Water', Money::fromString('0.65')),
            'juice' => new Product('juice', 'Juice', Money::fromString('1.00')),
            'soda' => new Product('soda', 'Soda', Money::fromString('1.50')),
        ];
        $this->productStock = [];
        $this->coinInventory = new CoinInventory();
        $this->changeCalculator = new GreedyChangeCalculator();
    }

    public function insertCoin(Coin $coin): void
    {
        $this->insertedCoins[] = $coin;
    }

    /**
     * Returns exactly the coins inserted by the customer and clears the
     * transaction. The machine's own coin fund is not touched.
     *
     * @return list<Coin>
     */
    public function returnCoins(): array
    {
        $returnedCoins = $this->insertedCoins;
        $this->insertedCoins = [];

        return $returnedCoins;
    }

    /**
     * Purchases the selected product with the currently inserted coins.
     *
     * Change is calculated against the machine's coin fund AS IT WAS BEFORE this
     * transaction: the inserted customer coins are not part of it yet and are
     * therefore never used to make change for their own purchase.
     *
     * @throws ProductNotFoundException    when the product does not exist
     * @throws OutOfStockException         when the product has no stock left
     * @throws InsufficientFundsException  when the inserted money does not cover the price
     * @throws InsufficientChangeException when the machine cannot provide exact change
     */
    public function selectProduct(string $productId): PurchaseResult
    {
        // 1. Validate the product exists.
        $product = $this->products[$productId]
            ?? throw new ProductNotFoundException(sprintf('Product "%s" does not exist', $productId));

        // 2. Validate the product is in stock.
        if (($this->productStock[$productId] ?? 0) < 1) {
            throw new OutOfStockException(sprintf('Product "%s" is out of stock', $product->name()));
        }

        // 3. Validate sufficient inserted money.
        $insertedAmount = $this->insertedAmount();
        if ($insertedAmount->isZero()) {
            throw new InsufficientFundsException('No coins inserted');
        }

        $price = $product->price();
        if (!$insertedAmount->greaterThanOrEqual($price)) {
            throw new InsufficientFundsException(sprintf(
                'Insufficient funds for "%s": inserted %s, price %s',
                $product->name(),
                (string) $insertedAmount,
                (string) $price
            ));
        }

        // 4./5. Calculate the required change against the pre-transaction fund only.
        $changeDue = $insertedAmount->subtract($price);
        $changeCoins = $this->changeCalculator->calculate(
            $changeDue->cents(),
            $this->coinInventory->quantities()
        );

        // 6. Every validation passed - commit the transaction atomically.
        --$this->productStock[$productId];

        foreach ($this->insertedCoins as $coin) {
            $this->coinInventory->addCoins($coin);
        }
        foreach ($changeCoins as $coin) {
            $this->coinInventory->removeCoins($coin);
        }

        $this->insertedCoins = [];

        return new PurchaseResult($product, $changeCoins);
    }

    /**
     * Replaces the product stock and the coin fund with a service configuration.
     * Both are treated as full replacements, not additive changes.
     *
     * The whole configuration is validated before any machine state is modified,
     * so an invalid configuration leaves the machine completely unchanged.
     *
     * @param array<string, int> $productStock   product id => units in stock
     * @param array<int, int>    $coinQuantities denomination (cents) => quantity
     *
     * @throws InvalidServiceOperationException when coins are inserted
     * @throws ProductNotFoundException         when the configuration names an unknown product
     * @throws InvalidCoinException             when a denomination is not accepted
     * @throws \InvalidArgumentException        when any configured quantity is negative
     */
    public function service(array $productStock, array $coinQuantities): void
    {
        if ([] !== $this->insertedCoins) {
            throw new InvalidServiceOperationException(
                'Cannot service the machine while customer coins are inserted'
            );
        }

        // Build and validate everything before touching current state.
        $newCoinInventory = new CoinInventory($coinQuantities);

        foreach ($productStock as $stockProductId => $quantity) {
            if (!isset($this->products[$stockProductId])) {
                throw new ProductNotFoundException(sprintf(
                    'Unknown product "%s" in service configuration',
                    $stockProductId
                ));
            }

            if ($quantity < 0) {
                throw new \InvalidArgumentException(sprintf(
                    'Stock quantity for product "%s" cannot be negative',
                    $stockProductId
                ));
            }
        }

        // Commit.
        $this->productStock = $productStock;
        $this->coinInventory = $newCoinInventory;
    }

    /**
     * Current stock per product id. Read-only snapshot of the aggregate state.
     *
     * @return array<string, int>
     */
    public function getProductStock(): array
    {
        return $this->productStock;
    }

    /**
     * Current change fund as denomination (cents) => quantity.
     * Read-only snapshot: the returned array is a copy.
     *
     * @return array<int, int>
     */
    public function getCoinInventory(): array
    {
        return $this->coinInventory->quantities();
    }

    /**
     * Total value currently inserted by the customer, in cents.
     */
    public function getInsertedTotal(): int
    {
        return $this->insertedAmount()->cents();
    }

    private function insertedAmount(): Money
    {
        $totalCents = 0;
        foreach ($this->insertedCoins as $coin) {
            $totalCents += $coin->toCents();
        }

        return Money::fromCents($totalCents);
    }
}
