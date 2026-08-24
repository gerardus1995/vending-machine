<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\ValueObject\Coin;
use App\Domain\VendingMachine;

/**
 * Inserts a coin into the current customer transaction.
 */
final class InsertCoinAction
{
    public function __construct(private readonly VendingMachine $vendingMachine) {}

    public function execute(Coin $coin): void
    {
        $this->vendingMachine->insertCoin($coin);
    }
}
