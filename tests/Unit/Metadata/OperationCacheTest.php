<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationCache::class)]
final class OperationCacheTest extends TestCase
{
    public function testAllOptionsAreUndefinedByDefault(): void
    {
        $cache = new OperationCache();

        self::assertNull($cache->enabled);
        self::assertNull($cache->ttl);
        self::assertNull($cache->vary);
    }

    public function testItPreservesExplicitlyDisabledState(): void
    {
        $cache = new OperationCache(enabled: false);

        self::assertFalse($cache->enabled);
    }

    public function testItPreservesExplicitlyEnabledState(): void
    {
        $cache = new OperationCache(enabled: true);

        self::assertTrue($cache->enabled);
    }

    public function testItPreservesTtl(): void
    {
        $cache = new OperationCache(ttl: 300);

        self::assertSame(300, $cache->ttl);
    }

    public function testItPreservesVaryHeaders(): void
    {
        $vary = [
            'Accept-Language',
            'Accept',
        ];

        $cache = new OperationCache(vary: $vary);

        self::assertSame($vary, $cache->vary);
    }

    public function testItPreservesAllOptionsTogether(): void
    {
        $cache = new OperationCache(
            enabled: true,
            ttl: 300,
            vary: [
                'Accept-Language',
                'Accept',
            ],
        );

        self::assertTrue($cache->enabled);
        self::assertSame(300, $cache->ttl);
        self::assertSame(
            [
                'Accept-Language',
                'Accept',
            ],
            $cache->vary,
        );
    }
}
