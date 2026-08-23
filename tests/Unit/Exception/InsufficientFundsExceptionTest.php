<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Domain\Exception\InsufficientFundsException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class InsufficientFundsExceptionTest extends TestCase
{
    public function testIsDomainException(): void
    {
        $this->assertInstanceOf(\DomainException::class, new InsufficientFundsException());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $this->expectException(InsufficientFundsException::class);

        throw new InsufficientFundsException('Test message');
    }
}
