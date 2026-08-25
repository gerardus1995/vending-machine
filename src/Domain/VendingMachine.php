<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Calculator\GreedyChangeCalculator;
use App\Domain\Catalogue\ProductCatalogue;
use App\Domain\Entity\Product;
use App\Domain\Exception\InsufficientChangeException;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\InvalidCoinException;
use App\Domain\Exception\InvalidServiceOperationException;
use App\Domain\Exception\OutOfStockException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\Inventory\CoinInventory;
use App\Domain\Inventory\CoinTransaction;
use App\Domain\Inventory\ProductInventory;
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
    private ProductCatalogue $catalogue;

    private ProductInventory $productInventory;

    private CoinInventory $coinInventory;

    private CoinTransaction $transaction;

    private readonly GreedyChangeCalculator $changeCalculator;

    public function __construct()
    {
        $this->catalogue = new ProductCatalogue([
            'water' => new Product('water', 'Water', Money::fromString('0.65')),
            'juice' => new Product('juice', 'Juice', Money::fromString('1.00')),
            'soda' => new Product('soda', 'Soda', Money::fromString('1.50')),
        ]);
        $this->productInventory = new ProductInventory($this->catalogue->knownIds());
        $this->coinInventory = new CoinInventory();
        $this->transaction = new CoinTransaction();
        $this->changeCalculator = new GreedyChangeCalculator();
    }

    public function insertCoin(Coin $coin): void
    {
        $this->transaction->insert($coin);
    }

    /**
     * Returns exactly the coins inserted by the customer and clears the
     * transaction. The machine's own coin fund is not touched.
     *
     * @return list<Coin>
     */
    public function returnCoins(): array
    {
        return $this->transaction->drain();
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
        $product = $this->catalogue->get($productId);

        // 2. Validate the product is in stock.
        if ($this->productInventory->quantityOf($productId) < 1) {
            throw new OutOfStockException(sprintf('Product "%s" is out of stock', $product->name()));
        }

        // 3. Validate sufficient inserted money.
        if ($this->transaction->isEmpty()) {
            throw new InsufficientFundsException('No coins inserted');
        }

        $insertedAmount = $this->transaction->amount();
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
        $this->productInventory->removeUnits($productId);

        foreach ($this->transaction->drain() as $coin) {
            $this->coinInventory->addCoins($coin);
        }
        foreach ($changeCoins as $coin) {
            $this->coinInventory->removeCoins($coin);
        }

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
        if (!$this->transaction->isEmpty()) {
            throw new InvalidServiceOperationException(
                'Cannot service the machine while customer coins are inserted'
            );
        }

        // Build and validate everything before touching current state.
        $newCoinInventory = new CoinInventory($coinQuantities);
        $newProductInventory = new ProductInventory(
            $this->catalogue->knownIds(),
            $productStock
        );

        // Commit.
        $this->productInventory = $newProductInventory;
        $this->coinInventory = $newCoinInventory;
    }

    /**
     * Current stock per product id. Read-only snapshot of the aggregate state.
     *
     * @return array<string, int>
     */
    public function getProductStock(): array
    {
        return $this->productInventory->quantities();
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
        return $this->transaction->amount()->cents();
    }
}
