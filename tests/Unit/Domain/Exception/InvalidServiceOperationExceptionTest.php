<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exception;

use App\Domain\Exception\InvalidServiceOperationException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class InvalidServiceOperationExceptionTest extends TestCase
{
    public function testIsDomainException(): void
    {
        $this->assertInstanceOf(\DomainException::class, new InvalidServiceOperationException());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $this->expectException(InvalidServiceOperationException::class);

        throw new InvalidServiceOperationException('Test message');
    }
}
