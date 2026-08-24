<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\ValueObject\Coin;
use App\Domain\VendingMachine;

/**
 * Returns the inserted coins to the customer and clears the transaction.
 */
final class ReturnCoinAction
{
    public function __construct(private readonly VendingMachine $vendingMachine) {}

    /**
     * @return list<Coin>
     */
    public function execute(): array
    {
        return $this->vendingMachine->returnCoins();
    }
}
