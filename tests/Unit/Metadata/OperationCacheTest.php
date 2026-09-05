<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByHeader;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(OperationCache::class)]
final class OperationCacheTest extends TestCase
{
    public function testItPreservesTtl(): void
    {
        $cache = new OperationCache(ttl: 300);

        self::assertSame(300, $cache->ttl);
    }

    public function testVaryByIsEmptyByDefault(): void
    {
        $cache = new OperationCache(ttl: 300);

        self::assertSame([], $cache->varyBy);
    }

    public function testItPreservesVaryDefinitions(): void
    {
        $varyBy = [
            new VaryByHeader('Accept-Language'),
            new VaryByResolver(TestVaryResolver::class),
        ];

        $cache = new OperationCache(
            ttl: 300,
            varyBy: $varyBy,
        );

        self::assertSame($varyBy, $cache->varyBy);
    }
}

final class TestVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return 'test';
    }
}
