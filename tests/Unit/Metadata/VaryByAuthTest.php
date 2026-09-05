<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Metadata\VaryBy;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByAuth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VaryByAuth::class)]
final class VaryByAuthTest extends TestCase
{
    public function testItIsAVaryDefinition(): void
    {
        self::assertInstanceOf(
            VaryBy::class,
            new VaryByAuth(),
        );
    }
}
