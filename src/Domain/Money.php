<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\InvalidMoneyException;

final class Money
{
    private readonly int $cents;

    /**
     * @throws InvalidMoneyException
     */
    public function __construct(int $cents)
    {
        if ($cents < 0) {
            throw new InvalidMoneyException('Money amount cannot be negative');
        }
        $this->cents = $cents;
    }

    public function __toString(): string
    {
        // Format as decimal with two decimal places
        return sprintf('%.2f', $this->cents / 100);
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * @throws InvalidMoneyException
     */
    public static function fromString(string $amount): self
    {
        // Remove any leading/trailing whitespace
        $amount = trim($amount);
        // Check if the string matches a decimal with up to two decimal places
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidMoneyException('Invalid money format');
        }
        // Convert to cents
        $cents = (int) round(floatval($amount) * 100);
        // Ensure non-negative (though regex already ensures no minus sign)
        if ($cents < 0) {
            throw new InvalidMoneyException('Money amount cannot be negative');
        }

        return new self($cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function add(Money $money): self
    {
        return new self($this->cents + $money->cents());
    }

    public function subtract(Money $money): self
    {
        $result = $this->cents - $money->cents();
        if ($result < 0) {
            throw new InvalidMoneyException('Subtraction would result in negative money');
        }

        return new self($result);
    }

    public function greaterThan(Money $money): bool
    {
        return $this->cents > $money->cents();
    }

    public function greaterThanOrEqual(Money $money): bool
    {
        return $this->cents >= $money->cents();
    }

    public function equals(Money $money): bool
    {
        return $this->cents === $money->cents();
    }

    public function isZero(): bool
    {
        return 0 === $this->cents;
    }
}
