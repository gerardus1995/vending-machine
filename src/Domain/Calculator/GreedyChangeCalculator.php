<?php

declare(strict_types=1);

namespace App\Domain\Calculator;

use App\Domain\Exception\InsufficientChangeException;
use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;

/**
 * Calculates change using the greedy algorithm, taking the largest
 * available denomination first.
 *
 * The challenge denominations {5, 10, 25, 100} form a canonical
 * coin system, so greedy produces an optimal solution.
 */
final class GreedyChangeCalculator
{
    /**
     * Calculates exact change without mutating anything: the caller remains free
     * to decide whether (and in which order) to apply the result.
     *
     * @param array<int, int> $availableCoins mapping of denomination (cents) to available quantity
     *
     * @return list<Coin> the actual change coins, highest denomination first
     *
     * @throws \InvalidArgumentException   when the requested amount is negative
     * @throws InsufficientChangeException when exact change cannot be made
     */
    public function calculate(int $amountCents, array $availableCoins): array
    {
        if ($amountCents < 0) {
            throw new \InvalidArgumentException('Change amount cannot be negative');
        }

        $change = [];
        $remaining = $amountCents;

        $denominations = array_keys($availableCoins);
        rsort($denominations);

        foreach ($denominations as $denomination) {
            if (0 === $remaining) {
                break;
            }

            $used = min(intdiv($remaining, $denomination), $availableCoins[$denomination]);

            for ($i = 0; $i < $used; ++$i) {
                $change[] = Coin::fromCents($denomination);
            }

            $remaining -= $used * $denomination;
        }

        if ($remaining > 0) {
            throw new InsufficientChangeException(sprintf(
                'Cannot make exact change of %s from the available coins',
                (string) Money::fromCents($amountCents)
            ));
        }

        return $change;
    }
}
