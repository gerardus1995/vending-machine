<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exception\InvalidCoinException;
use App\Domain\Exception\InvalidServiceOperationException;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\VendingMachine;

/**
 * Applies a service configuration: replaces product stock and the change fund.
 */
final class ServiceMachineAction
{
    public function __construct(private readonly VendingMachine $vendingMachine) {}

    /**
     * Domain exceptions are not translated here: presenting failures is the
     * responsibility of the interface layer on top of this action.
     *
     * @param array<string, int> $productStock   product id => units in stock
     * @param array<int, int>    $coinQuantities denomination (cents) => quantity
     *
     * @throws InvalidServiceOperationException when customer coins are inserted
     * @throws ProductNotFoundException         when the configuration names an unknown product
     * @throws InvalidCoinException             when a denomination is not accepted
     * @throws \InvalidArgumentException        when any configured quantity is negative
     */
    public function execute(array $productStock, array $coinQuantities): void
    {
        $this->vendingMachine->service($productStock, $coinQuantities);
    }
}
