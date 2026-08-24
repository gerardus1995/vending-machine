## Money Refactoring Plan

### Goal
Refactor the Money::fromString() method to remove all floating-point arithmetic while preserving existing behavior.

### Changes
1. Replace float-based parsing in Money::fromString() with pure string/integer manipulation
2. Maintain the same validation rules and output format
3. Support all specified input formats:
   - "0.05" -> 5 cents
   - "0.10" -> 10 cents
   - "0.25" -> 25 cents
   - "1" -> 100 cents
   - "1.00" -> 100 cents
   - "1.5" -> 150 cents
4. Reject invalid formats (non-numeric, wrong decimal places, negative, etc.)

### Files to Modify
- `src/Domain/Money.php` - Update the fromString() method

### Testing Strategy
- Run existing PHPUnit tests for Money to ensure no regressions
- Add additional test cases if needed to cover edge cases
- Run PHPStan to ensure type safety
- Run PHP-CS-Fixer to maintain code style

### Verification Steps
1. Execute PHPUnit for MoneyTest
2. Execute PHPStan analysis
3. Execute PHP-CS-Fixer (dry-run) to check for style issues
4. Confirm all tests pass and no new issues are introduced