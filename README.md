# Vending Machine Kata

This is a PHP 8.3 implementation of a vending machine kata. The project follows a clean architecture with Domain, Application, and CLI layers.

## Requirements

### Functional
- Accepts only these coins: **0.05**, **0.10**, **0.25** and **1.00**.
- Sells three products at fixed prices: **Water 0.65**, **Juice 1.00** and **Soda 1.50** - every item has a count, a price and a selector.
- Supported actions: insert one accepted coin, `RETURN-COIN` (refund all inserted money), `GET <product>` (dispense the product and return change) and `SERVICE` (replace product stock and change fund).
- The machine always tracks available items, available change and currently inserted money.
- A product is dispensed only when enough money has been inserted; otherwise the appropriate error is reported and no state changes.

### Technical
- PHP 8.3+
- Composer
- PHPUnit (behavioural test suite), PHPStan (static analysis) and PHP-CS-Fixer (code style)
- Docker / Docker Compose (optional, for easy evaluation)

## Getting Started

### Prerequisites
- Docker and Docker Compose

### Running with Docker

1. Build the Docker image:
   ```bash
   docker compose build
   ```

2. Start a container and open a shell:
   ```bash
   docker compose run --rm app bash
   ```

3. Inside the container, install dependencies:
   ```bash
   composer install
   ```

### Running the Vending Machine CLI

```bash
docker compose run --rm app php bin/vending-machine
```

Commands are read line by line from STDIN (case-insensitive):

| Command | Example | Effect |
|---|---|---|
| `<amount>` / `INSERT <amount>` | `1`, `0.25`, `INSERT 0.05` | insert a coin (0.05, 0.10, 0.25, 1.00) |
| `RETURN-COIN` | `RETURN-COIN` | hand back all inserted coins |
| `GET <product>` / `GET-<product>` | `GET-SODA`, `GET Juice` | dispense a product |
| `SERVICE <id>:<qty>,... <coin>:<qty>,...` | `SERVICE water:5,juice:5,soda:5 5:10,10:10,25:10,100:10` | replace stock and change fund |

Responses use the challenge vocabulary (`SODA`, `WATER, 0.25, 0.10`, `0.10, 0.10`);
failures are rendered as `ERROR: ...` lines. Unknown or malformed input is answered
with an `ERROR: ...` line or a USAGE hint; blank lines are ignored.

### Running Tests
```bash
composer test
```

### Running PHPStan
```bash
composer stan
```

### Running PHP-CS-Fixer
Check for fixes:
```bash
composer cs-check
```
Apply fixes:
```bash
composer cs-fix
```

## Project Structure
- `src/Domain` - Domain entities, value objects, inventories, and exceptions.
- `src/Application` - Application actions (thin use cases around the domain).
- `src/Cli` - Command-line interface parsing and output formatting.
- `tests/Unit` - Unit tests, mirroring the structure of `src/`.
- `tests/Integration` - End-to-end tests running the real CLI binary.

## Architecture (Hexagonal & DDD)

Dependencies point strictly inward (`Cli -> Application -> Domain`); nothing inside
`Domain` knows about outer layers, so the domain is testable without Docker, CLI
or any infrastructure.

| Hexagonal concept | Where it lives here |
|---|---|
| Driving adapter | `Cli/VendingMachineCli` (plus the `bin/vending-machine` entry point) |
| Application core (use cases) | `Application/*Action` |
| Domain model (entity, value objects, inventories, domain service) | `Domain/*` |
| Driven adapters / ports | none yet - no persistence or external services exist; the seams are ready |

DDD tactical patterns in use:

- **Aggregate root**: `VendingMachine` owns and mutates every piece of machine
  state transactionally; nothing outside it touches inventories directly.
- **Value objects**: `Money`, `Coin`.
- **Entity**: `Product` (identity by id), held by an explicit `ProductCatalogue`.
- **Inventories with invariants**: `CoinInventory`, `ProductInventory`, `CoinTransaction`.
- **Domain service**: `GreedyChangeCalculator`.
- **Ubiquitous language**: names mirror the challenge vocabulary verbatim.

Two deliberate deviations from textbook hexagonal/DDD, accepted because a
formalism with a single implementation and no second consumer would be
speculative (see CLAUDE.md §3):

1. Use-case actions are concrete classes instead of port interfaces - the seams
   to extract interfaces already exist when a second adapter appears.
2. No repositories or domain events - there is no persistence yet; both are the
   natural next evolution once storage is introduced.

## Design Decisions

### Layering
Dependencies point one way only: `Domain <- Application <- Cli`. The Domain holds all
business rules and knows nothing about the layers above it; Application orchestrates
the aggregate; the CLI only parses input and renders output. Every layer is tested
with real objects - no mocks are needed anywhere in the suite.

### Why Application actions exist (even when thin)
Each supported operation is one concrete action class (`InsertCoinAction`,
`ReturnCoinAction`, `PurchaseProductAction`, `ServiceMachineAction`) that calls the
aggregate and returns its natural domain result. Some are near-delegates by choice:
the uniform seam keeps every caller identical, decouples callers from aggregate method
names, and gives future concerns (outcome mapping, logging) a home outside the Domain.
They deliberately return domain objects and let domain exceptions propagate - failure
presentation belongs to the interface layer, not to Application.

### Why there are no interfaces, repositories, factories, or command buses
Abstractions must earn their place. Each concept has exactly one implementation and no
second consumer, so an interface would only restate a contract; there is no persistence,
so repositories cannot exist; construction is a plain `new`, so factories add nothing;
there are four operations, so a command bus would be ceremony. The seams make it cheap
to introduce such abstractions when a concrete second need appears - not before.

### Transaction atomicity and validation order
Every purchase validates completely before mutating anything, in a fixed order:
product exists -> in stock -> sufficient funds -> exact change possible. Change is
calculated against a snapshot of the coin fund taken before any mutation; only then
does the machine commit (decrease stock, absorb the inserted coins, remove the change
coins, clear the transaction). A failed purchase leaves product stock, coin fund, and
inserted coins bit-for-bit unchanged - asserted via snapshot-equality tests covering
all four failure modes.

### Inserted coins are not part of the change fund
Customer coins live in a separate transaction and are never used as change for their
own purchase: with an empty fund, 1.00 inserted cannot buy Water (0.65) because the
machine cannot make 0.35 change - the customer's own coins do not count towards it.
The inserted money stays fully refundable through RETURN-COIN, which never touches the
machine fund.

### SERVICE uses replacement semantics
SERVICE replaces product stock and the change fund wholesale (not additively) and is
rejected while customer coins are inserted. The entire configuration is validated
before any state changes, so an invalid configuration leaves the machine completely
unchanged. Products omitted from the configuration become unavailable; explicit `0`
stock is kept as "sold out", while zero coin quantities are simply not stored.
Product IDs in SERVICE are matched case-sensitively (unlike GET selectors).

### Fixed product catalogue
The three products (Water 0.65, Juice 1.00, Soda 1.50) and their selectors are fixed by
the challenge. SERVICE restocks them but cannot define new products: adding a product is
a code change, not runtime data, which keeps selectors, prices, and validation coherent.

### Change calculation
Greedy (largest denomination first, respecting available quantities). For canonical
systems like {5, 10, 25, 100} greedy produces the fewest coins; for arbitrary coin
systems it may be suboptimal (e.g. {1, 3, 4}) - accepted for this fixed set and
isolated in one replaceable class. Decision: **1.00 coins may be returned as change**
(e.g. paying 2x1.00 for Juice returns `JUICE, 1.00`). The challenge lists only <=0.25
as coin responses but does not forbid larger change; allowing any accepted denomination
is the simplest consistent reading.

### Empty transactions
Buying with nothing inserted raises `InsufficientFundsException` ("No coins inserted")
instead of a dedicated error: selecting a product without paying *is* insufficient
funds. The normal validation order still applies, so an unknown product or empty shelf
is reported before the missing payment.

### CLI error handling
Application never translates failures; they propagate to the CLI, which is the
presentation boundary. A single catch renders any domain rejection (`\DomainException`,
`\InvalidArgumentException`) as `ERROR: <message>` using the domain's own message, so
error wording stays defined in exactly one place. Unexpected throwables are deliberately
not masked: a bug should crash loudly rather than masquerade as a business error.