<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Entity\Product;
use App\Domain\Result\PurchaseResult;
use App\Domain\ValueObject\Coin;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PurchaseResultTest extends TestCase
{
    public function testCarriesTheProductAndTheActualChangeCoins(): void
    {
        $product = new Product('water', 'Water', Money::fromString('0.65'));
        $result = new PurchaseResult($product, [Coin::TWENTY_FIVE, Coin::TEN]);

        self::assertSame($product, $result->getProduct());
        self::assertSame([Coin::TWENTY_FIVE, Coin::TEN], $result->getChangeCoins());
    }

    public function testChangeTotalIsDerivedFromTheChangeCoins(): void
    {
        $product = new Product('water', 'Water', Money::fromString('0.65'));
        $result = new PurchaseResult($product, [Coin::TWENTY_FIVE, Coin::TEN]);

        self::assertSame(35, $result->getChangeTotal()->cents());
        self::assertSame('0.35', (string) $result->getChangeTotal());
    }

    public function testExactPaymentResultsInEmptyChangeAndZeroTotal(): void
    {
        $product = new Product('juice', 'Juice', Money::fromString('1.00'));
        $result = new PurchaseResult($product, []);

        self::assertSame([], $result->getChangeCoins());
        self::assertTrue($result->getChangeTotal()->isZero());
    }
}
