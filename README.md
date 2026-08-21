# Vending Machine Kata

This is a PHP 8.3 implementation of a vending machine kata. The project follows a clean architecture with Domain, Application, and CLI layers.

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

### Running Tests
```bash
php vendor/bin/phpunit
```

### Running PHPStan
```bash
php vendor/bin/phpstan analyse
```

### Running PHP-CS-Fixer
Check for fixes:
```bash
php vendor/bin/php-cs-fixer fix --dry-run --diff
```
Apply fixes:
```bash
php vendor/bin/php-cs-fixer fix
```

## Project Structure
- `src/Domain` - Domain entities, value objects, exceptions, and interfaces.
- `src/Application` - Application services and use cases.
- `src/Cli` - Command-line interface parsing and output formatting.
- `tests/Unit` - Unit tests.
- `tests/Integration` - Integration tests.