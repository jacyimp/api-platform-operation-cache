<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(OperationCache::class)]
final class OperationCacheTest extends TestCase
{
    public function testItUsesMinimalDefaults(): void
    {
        $cache = new OperationCache(ttl: 300);

        self::assertSame(300, $cache->ttl);
        self::assertSame([], $cache->varyByHeaders);
        self::assertFalse($cache->varyByAuth);
        self::assertNull($cache->varyByResolver);
        self::assertNull($cache->when);
        self::assertSame([], $cache->excludeResponseHeaders);
        self::assertTrue($cache->excludeDefaultResponseHeaders);
        self::assertNull($cache->responseMutator);
    }

    public function testItPreservesFullConfiguration(): void
    {
        $cache = new OperationCache(
            ttl: 600,
            varyByHeaders: [
                'Accept-Language',
                'X-Tenant',
            ],
            varyByAuth: TestAuthIdentityResolver::class,
            varyByResolver: TestVaryResolver::class,
            when: TestCacheCondition::class,
            excludeResponseHeaders: [
                'X-Request-Id',
            ],
            excludeDefaultResponseHeaders: false,
            responseMutator: TestResponseMutator::class,
        );

        self::assertSame(600, $cache->ttl);
        self::assertSame(
            [
                'Accept-Language',
                'X-Tenant',
            ],
            $cache->varyByHeaders,
        );
        self::assertSame(
            TestAuthIdentityResolver::class,
            $cache->varyByAuth,
        );
        self::assertSame(
            TestVaryResolver::class,
            $cache->varyByResolver,
        );
        self::assertSame(
            TestCacheCondition::class,
            $cache->when,
        );
        self::assertSame(
            [
                'X-Request-Id',
            ],
            $cache->excludeResponseHeaders,
        );
        self::assertFalse($cache->excludeDefaultResponseHeaders);
        self::assertSame(
            TestResponseMutator::class,
            $cache->responseMutator,
        );
    }

    public function testItSupportsBuiltInAuthVariation(): void
    {
        $cache = new OperationCache(
            ttl: 300,
            varyByAuth: true,
        );

        self::assertTrue($cache->varyByAuth);
    }
}

final class TestVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return 'vary';
    }
}

final class TestAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return 'user-42';
    }
}

final class TestCacheCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return true;
    }
}

final class TestResponseMutator implements ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response {
        return $response;
    }

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response {
        return $response;
    }
}
