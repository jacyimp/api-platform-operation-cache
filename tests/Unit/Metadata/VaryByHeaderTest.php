<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Metadata\VaryByHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VaryByHeader::class)]
final class VaryByHeaderTest extends TestCase
{
    public function testItPreservesHeader(): void
    {
        $vary = new VaryByHeader('Accept-Language');

        self::assertSame('Accept-Language', $vary->header);
    }
}
