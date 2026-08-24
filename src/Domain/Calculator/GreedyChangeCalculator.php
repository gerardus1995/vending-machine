<?php

declare(strict_types=1);

namespace App\Domain\Calculator;

use App\Domain\Exception\InsufficientChangeException;
use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;

/**
 * Calculates change with the classic greedy algorithm: repeatedly take as many
 * coins of the largest denomination as the remaining amount and availability allow.
 *
 * Trade-off note: greedy produces the fewest possible coins for canonical
 * denomination systems such as this challenge's {5, 10, 25, 100}. It does NOT
 * guarantee that a solution exists (nor optimality) for arbitrary coin systems -
 * e.g. with denominations {1, 3, 4} and amount 6 greedy gives 4+1+1 while 3+3 is
 * optimal. If the denomination set ever becomes non-canonical, only this class
 * needs to change; its contract stays the same.
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
