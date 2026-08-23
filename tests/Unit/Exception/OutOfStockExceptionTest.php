<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Domain\Exception\OutOfStockException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class OutOfStockExceptionTest extends TestCase
{
    public function testIsDomainException(): void
    {
        $this->assertInstanceOf(\DomainException::class, new OutOfStockException());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $this->expectException(OutOfStockException::class);

        throw new OutOfStockException('Test message');
    }
}
