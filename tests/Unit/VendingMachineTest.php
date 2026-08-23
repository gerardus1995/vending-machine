<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Coin;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\InvalidServiceOperationException;
use App\Domain\Exception\OutOfStockException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\GreedyChangeCalculator;
use App\Domain\InMemoryProductRepository;
use App\Domain\Product;
use App\Domain\PurchaseResult;
use App\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class VendingMachineTest extends TestCase
{
    private VendingMachine $vendingMachine;
    private InMemoryProductRepository $productRepository;
    private GreedyChangeCalculator $changeCalculator;

    protected function setUp(): void
    {
        $this->productRepository = InMemoryProductRepository::createDefault();
        $this->changeCalculator = new GreedyChangeCalculator();
        $this->vendingMachine = new VendingMachine(
            $this->productRepository,
            $this->changeCalculator
        );
    }

    public function testInitiallyEmptyInventories(): void
    {
        $this->assertEquals([], $this->vendingMachine->getProductStock());
        $this->assertEquals([], $this->vendingMachine->getCoinInventory());
        $this->assertEquals(0, $this->vendingMachine->getInsertedTotal());
    }

    public function testInsertCoinAndReturnCoins(): void
    {
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE); // 25 cents
        $this->vendingMachine->insertCoin(Coin::TEN);         // 10 cents
        $this->vendingMachine->insertCoin(Coin::FIVE);        // 5 cents

        $inserted = $this->vendingMachine->returnCoins();
        $this->assertCount(3, $inserted);
        $this->assertContains(Coin::TWENTY_FIVE, $inserted);
        $this->assertContains(Coin::TEN, $inserted);
        $this->assertContains(Coin::FIVE, $inserted);

        // After returnCoins, the transaction should be cleared
        $this->assertEquals(0, $this->vendingMachine->getInsertedTotal());
    }

    public function testSelectProductSuccessExactChange(): void
    {
        // Set up the machine with one water in stock and no coins in inventory (we don't need change for exact change)
        $this->vendingMachine->service(
            ['water' => 1, 'juice' => 1, 'soda' => 1], // product stock
            [] // coin inventory - we don't need to give change
        );

        // Insert exactly 65 cents (price of Water)
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE); // 25
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE); // 25
        $this->vendingMachine->insertCoin(Coin::TEN);         // 10
        $this->vendingMachine->insertCoin(Coin::FIVE);        // 5

        $result = $this->vendingMachine->selectProduct('water');

        $this->assertInstanceOf(PurchaseResult::class, $result);
        $this->assertEquals('water', $result->product()->id());
        $this->assertEquals('Water', $result->product()->name());
        $this->assertEquals(0, $result->change()->cents()); // No change

        // Check state changes: product stock decreased (water), coin inventory updated
        $this->assertEquals(0, $this->vendingMachine->getProductStock()['water']); // water stock decreased from 1 to 0
        $this->assertEquals(1, $this->vendingMachine->getProductStock()['juice']); // juice unchanged
        $this->assertEquals(1, $this->vendingMachine->getProductStock()['soda']); // soda unchanged
        // Coin inventory: we inserted 2x25c, 1x10c, 1x5c (total 65) and gave back 0 change, so machine should have those coins
        $this->assertEquals(2, $this->vendingMachine->getCoinInventory()[25] ?? 0);
        $this->assertEquals(1, $this->vendingMachine->getCoinInventory()[10] ?? 0);
        $this->assertEquals(1, $this->vendingMachine->getCoinInventory()[5] ?? 0);
        // Transaction cleared
        $this->assertEquals(0, $this->vendingMachine->getInsertedTotal());
    }

    public function testSelectProductSuccessWithChange(): void
    {
        // First, set up the machine with a proper coin inventory for making change
        // Service the machine with some coins and product stock
        $this->vendingMachine->service(
            ['water' => 1, 'juice' => 1, 'soda' => 1], // product stock
            [5 => 10, 10 => 10, 25 => 10, 100 => 10]  // ample change fund
        );

        // Insert 200 cents (two dollars) for a Soda (150 cents) -> change 50 cents
        $this->vendingMachine->insertCoin(Coin::ONE_HUNDRED); // 100
        $this->vendingMachine->insertCoin(Coin::ONE_HUNDRED); // 100

        $result = $this->vendingMachine->selectProduct('soda');

        $this->assertInstanceOf(PurchaseResult::class, $result);
        $this->assertEquals('soda', $result->product()->id());
        $this->assertEquals('Soda', $result->product()->name());
        $this->assertEquals(50, $result->change()->cents());

        // Check state: Soda stock decreased by 1 (assuming default stock is 1)
        $this->assertEquals(0, $this->vendingMachine->getProductStock()['soda']);
        // Coin inventory: inserted two 100c coins, returned change for 50c
        // For 50c change, greedy algorithm with available coins [5,10,25,100] will use 2x25c
        $this->assertEquals(12, $this->vendingMachine->getCoinInventory()[100] ?? 0); // initial 10 + inserted 2 = 12
        $this->assertEquals(8, $this->vendingMachine->getCoinInventory()[25] ?? 0);    // initial 10 - used 2 for change = 8
        $this->assertEquals(10, $this->vendingMachine->getCoinInventory()[10] ?? 0);  // unchanged
        $this->assertEquals(10, $this->vendingMachine->getCoinInventory()[5] ?? 0);   // unchanged
        // Transaction cleared
        $this->assertEquals(0, $this->vendingMachine->getInsertedTotal());
    }

    public function testSelectProductInsufficientFunds(): void
    {
        // Set up juice in stock so we get InsufficientFunds, not OutOfStock
        $this->vendingMachine->service(
            ['water' => 1, 'juice' => 1, 'soda' => 1],
            [] // coin inventory doesn't matter for this test
        );

        // Insert 50 cents, try to buy Juice (100 cents)
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE); // 25
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE); // 25

        $this->expectException(InsufficientFundsException::class);
        $this->vendingMachine->selectProduct('juice');

        // State should be unchanged: coin inventory should still be empty, inserted coins should still be there (until we return or make a purchase)
        // Actually, on failure, no state should be mutated. So the inserted coins are still in the transaction.
        $this->assertEquals(50, $this->vendingMachine->getInsertedTotal());
        $this->assertEquals([], $this->vendingMachine->getCoinInventory()); // No change to machine's inventory
        $this->assertEquals(1, $this->vendingMachine->getProductStock()['juice']); // Juice stock unchanged
    }

    public function testSelectProductProductNotFound(): void
    {
        // Ensure product stock is set so we don't get OutOfStock
        $this->vendingMachine->service(
            ['water' => 1, 'juice' => 1, 'soda' => 1],
            []
        );

        $this->vendingMachine->insertCoin(Coin::ONE_HUNDRED);

        $this->expectException(ProductNotFoundException::class);
        $this->vendingMachine->selectProduct('nonexistent');

        // State unchanged: inserted coin still in transaction, machine inventory unchanged
        $this->assertEquals(100, $this->vendingMachine->getInsertedTotal());
        $this->assertEquals([], $this->vendingMachine->getCoinInventory());
    }

    public function testSelectProductOutOfStock(): void
    {
        // Set up initial stock: one water
        $this->vendingMachine->service(
            ['water' => 1, 'juice' => 1, 'soda' => 1],
            [] // coin inventory
        );

        // First, buy the last unit of a product to deplete stock
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE);
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE);
        $this->vendingMachine->insertCoin(Coin::TEN);
        $this->vendingMachine->insertCoin(Coin::FIVE); // 65 for Water

        $this->vendingMachine->selectProduct('water'); // This should succeed and deplete Water stock

        // Now try to buy Water again
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE);
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE);
        $this->vendingMachine->insertCoin(Coin::TEN);
        $this->vendingMachine->insertCoin(Coin::FIVE); // 65 again

        $this->expectException(OutOfStockException::class);
        $this->vendingMachine->selectProduct('water');

        // State: the inserted coins for the failed attempt should still be in the transaction
        $this->assertEquals(65, $this->vendingMachine->getInsertedTotal());
        // The machine's coin inventory should have the coins from the successful first transaction
        // First transaction: inserted 65 (2x25c, 1x10c, 1x5c) and gave 0 change, so machine has those coins.
        $this->assertEquals(2, $this->vendingMachine->getCoinInventory()[25] ?? 0);
        $this->assertEquals(1, $this->vendingMachine->getCoinInventory()[10] ?? 0);
        $this->assertEquals(1, $this->vendingMachine->getCoinInventory()[5] ?? 0);
        // Water stock should be 0 (since we bought the last one)
        $this->assertEquals(0, $this->vendingMachine->getProductStock()['water']);
    }

    public function testSelectProductUnableToMakeChange(): void
    {
        // Set up the machine with no change fund (or insufficient change) and then try to buy a product requiring change.
        // We'll use the service method to set the coin inventory to empty.
        // We need to set product stock as well.

        // First, service the machine with empty coin inventory and some product stock
        $this->vendingMachine->service(
            ['water' => 1, 'juice' => 1, 'soda' => 1], // product stock
            [] // empty coin inventory
        );

        // Now insert a dollar (100c) to buy a Water (65c) -> change 35c
        // If the machine has no coins, it cannot make change.

        $this->vendingMachine->insertCoin(Coin::ONE_HUNDRED); // 100

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot make exact change with available coins');
        $this->vendingMachine->selectProduct('water');

        // State: the inserted dollar should still be in the transaction (because the purchase failed)
        $this->assertEquals(100, $this->vendingMachine->getInsertedTotal());
        // Machine's coin inventory should still be empty (no transaction succeeded)
        $this->assertEquals([], $this->vendingMachine->getCoinInventory());
        // Product stock should be unchanged (Water still available)
        $this->assertEquals(1, $this->vendingMachine->getProductStock()['water']);
    }

    public function testServiceSuccess(): void
    {
        // Set up new product stock and coin inventory
        $newProductStock = ['water' => 5, 'juice' => 0, 'soda' => 2];
        $newCoinInventory = [5 => 10, 10 => 10, 25 => 10, 100 => 10];

        $this->vendingMachine->service($newProductStock, $newCoinInventory);

        $this->assertEquals($newProductStock, $this->vendingMachine->getProductStock());
        $this->assertEquals($newCoinInventory, $this->vendingMachine->getCoinInventory());
    }

    public function testServiceFailsWhenCoinsInserted(): void
    {
        // Insert a coin first
        $this->vendingMachine->insertCoin(Coin::TWENTY_FIVE);

        $this->expectException(InvalidServiceOperationException::class);
        $this->vendingMachine->service([], []);

        // State should be unchanged: the coin should still be in the transaction
        $this->assertEquals(25, $this->vendingMachine->getInsertedTotal());
    }

    public function testAtomicityOnFailureNoStateMutation(): void
    {
        // Set up the machine with a known coin inventory and product stock
        $initialProductStock = ['water' => 1];
        $initialCoinInventory = [25 => 4]; // four quarters
        $this->vendingMachine->service($initialProductStock, $initialCoinInventory);

        // Insert a coin (a dime) so we have some transaction
        $this->vendingMachine->insertCoin(Coin::TEN); // 10 cents

        // Record the state before the failed operation
        $productStockBefore = $this->vendingMachine->getProductStock();
        $coinInventoryBefore = $this->vendingMachine->getCoinInventory();
        $insertedBefore = $this->vendingMachine->getInsertedTotal();

        // Attempt to buy a product that doesn't exist
        $this->expectException(ProductNotFoundException::class);
        $this->vendingMachine->selectProduct('nonexistent');

        // After the exception, the state should be exactly as before
        $this->assertEquals($productStockBefore, $this->vendingMachine->getProductStock());
        $this->assertEquals($coinInventoryBefore, $this->vendingMachine->getCoinInventory());
        $this->assertEquals($insertedBefore, $this->vendingMachine->getInsertedTotal());
    }
}
