<?php

declare(strict_types=1);

namespace App\Domain;

interface ChangeCalculatorInterface
{
    /**
     * Calculate the change to return for a given amount using the available coins.
     *
     * @param int            $amountCents    the amount of change to give (in cents, non-negative)
     * @param array<int,int> $availableCoins An array mapping coin denomination (in cents) to the number of coins available.
     *                                       Example: [25 => 4, 10 => 3] means 4 quarters and 3 dimes available.
     *
     * @return array<int,int> an array mapping coin denomination (in cents) to the number of coins to return
     *
     * @throws \DomainException if exact change cannot be made with the available coins
     */
    public function calculate(int $amountCents, array $availableCoins): array;
}
