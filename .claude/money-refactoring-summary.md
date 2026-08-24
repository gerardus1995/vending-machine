## Money::fromString() Current Implementation

```php
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

        // Split into dollars and cents parts
        if (false !== strpos($amount, '.')) {
            $parts = explode('.', $amount);
            $dollars = $parts[0];
            $centsPart = $parts[1];

            // Ensure cents part has exactly 2 digits
            if (1 === strlen($centsPart)) {
                $centsPart .= '0';
            } elseif (strlen($centsPart) > 2) {
                // This shouldn't happen due to regex, but just in case
                throw new InvalidMoneyException('Invalid money format');
            }

            $cents = (int) $dollars * 100 + (int) $centsPart;
        } else {
            // No decimal point, whole dollar amount
            $cents = (int) $amount * 100;
        }

        // Ensure non-negative (though regex already ensures no minus sign)
        if ($cents < 0) {
            throw new InvalidMoneyException('Money amount cannot be negative');
        }

        return new self($cents);
    }
```

## Updated MoneyTest.php (Relevant Addition)

```php
    public function testCanBeCreatedFromStringWithSpecificExamples(): void
    {
        $this->assertSame(5, Money::fromString('0.05')->cents());
        $this->assertSame(10, Money::fromString('0.10')->cents());
        $this->assertSame(25, Money::fromString('0.25')->cents());
        $this->assertSame(100, Money::fromString('1')->cents());
        $this->assertSame(100, Money::fromString('1.00')->cents());
        $this->assertSame(150, Money::fromString('1.5')->cents());
    }
```

## Regex Validation Analysis

**Pattern:** `/^\d+(\.\d{1,2})?$/`

**Valid Formats:**
- Integer dollar amounts: "0", "1", "5", "100"
- Decimal with 1 digit: "0.5", "1.5", "2.5" (interpreted as X.50 cents)
- Decimal with 2 digits: "0.05", "0.10", "0.25", "1.00", "1.50"
- Examples from requirements that work: "0.05"→5¢, "0.10"→10¢, "0.25"→25¢, "1"→100¢, "1.00"→100¢, "1.5"→150¢

**Invalid Formats:**
- Empty string or just ".", ".5"
- More than 2 decimal places: "0.123", "1.456"
- Non-numeric: "abc", "1.2a"
- Multiple dots: "1.2.3"
- Negative numbers: "-0.05" (caught by regex since it doesn't start with digit)
- Special cases: "1." (dot with no trailing digits - invalid)

**Appropriateness for Challenge:**
✅ **Appropriate** because:
- Handles all required formats correctly
- Rejects invalid formats appropriately
- Maintains existing validation behavior
- Successfully eliminated floating-point arithmetic while preserving functionality
- The regex approach was already working and correct for monetary validation