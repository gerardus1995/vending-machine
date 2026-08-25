<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exception;

use App\Domain\Exception\ProductNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProductNotFoundExceptionTest extends TestCase
{
    public function testIsDomainException(): void
    {
        $this->assertInstanceOf(\DomainException::class, new ProductNotFoundException());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $this->expectException(ProductNotFoundException::class);

        throw new ProductNotFoundException('Test message');
    }
}
