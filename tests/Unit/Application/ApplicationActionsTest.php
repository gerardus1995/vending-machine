<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\InsertCoinAction;
use App\Application\PurchaseProductAction;
use App\Application\ReturnCoinAction;
use App\Application\ServiceMachineAction;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\InvalidServiceOperationException;
use App\Domain\ValueObject\Coin;
use App\Domain\VendingMachine;
use PHPUnit\Framework\TestCase;

/**
 * The application actions are thin orchestrators over the domain aggregate:
 * these tests verify they return the natural domain results and let domain
 * exceptions propagate untouched.
 *
 * @internal
 *
 * @coversNothing
 */
final class ApplicationActionsTest extends TestCase
{
    private VendingMachine $machine;

    protected function setUp(): void
    {
        $this->machine = new VendingMachine();
        $this->machine->service(
            ['water' => 5, 'juice' => 5, 'soda' => 5],
            [5 => 10, 10 => 10, 25 => 10, 100 => 10]
        );
    }

    public function testInsertCoinAddsToTheTransaction(): void
    {
        $action = new InsertCoinAction($this->machine);

        $action->execute(Coin::TWENTY_FIVE);

        self::assertSame(25, $this->machine->getInsertedTotal());
    }

    public function testReturnCoinHandsBackExactlyTheInsertedCoins(): void
    {
        (new InsertCoinAction($this->machine))->execute(Coin::TEN);
        $action = new ReturnCoinAction($this->machine);

        $returnedCoins = $action->execute();

        self::assertSame([Coin::TEN], $returnedCoins);
        self::assertSame(0, $this->machine->getInsertedTotal());
    }

    public function testPurchaseReturnsTheDomainPurchaseResult(): void
    {
        (new InsertCoinAction($this->machine))->execute(Coin::ONE_HUNDRED);
        $action = new PurchaseProductAction($this->machine);

        $result = $action->execute('water');

        self::assertSame('Water', $result->getProduct()->name());
        self::assertSame([Coin::TWENTY_FIVE, Coin::TEN], $result->getChangeCoins());
    }

    public function testPurchaseLetsDomainExceptionsPropagate(): void
    {
        $action = new PurchaseProductAction($this->machine); // nothing inserted yet

        $this->expectException(InsufficientFundsException::class);

        $action->execute('water');
    }

    public function testServiceAppliesTheConfiguration(): void
    {
        $action = new ServiceMachineAction($this->machine);

        $action->execute(['juice' => 2], [25 => 1]);

        self::assertSame(['juice' => 2], $this->machine->getProductStock());
        self::assertSame([25 => 1], $this->machine->getCoinInventory());
    }

    public function testServiceLetsDomainExceptionsPropagate(): void
    {
        (new InsertCoinAction($this->machine))->execute(Coin::FIVE);
        $action = new ServiceMachineAction($this->machine);

        $this->expectException(InvalidServiceOperationException::class);

        $action->execute([], []);
    }
}
