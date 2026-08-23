<?php

declare(strict_types=1);

namespace App\Domain;

class GreedyChangeCalculator implements ChangeCalculatorInterface
{
    public function calculate(int $amountCents, array $availableCoins): array
    {
        if ($amountCents < 0) {
            throw new \InvalidArgumentException('Amount to change cannot be negative');
        }

        // Sort denominations in descending order
        $denominations = array_keys($availableCoins);
        rsort($denominations);

        $remaining = $amountCents;
        $change = [];

        foreach ($denominations as $denom) {
            if ($remaining <= 0) {
                break;
            }
            $available = $availableCoins[$denom] ?? 0;
            if ($available <= 0) {
                continue;
            }
            // How many coins of this denomination we can use
            $count = min(intdiv($remaining, $denom), $available);
            if ($count > 0) {
                $change[$denom] = $count;
                $remaining -= $count * $denom;
            }
        }

        if ($remaining > 0) {
            throw new \DomainException('Cannot make exact change with available coins');
        }

        return $change;
    }
}
