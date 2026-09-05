<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheException;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestAuthIdentityResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestCacheCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestCacheGroupResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestResponseMutator;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestVaryResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
        self::assertSame([], $cache->groups);
        self::assertNull($cache->groupResolver);
        self::assertTrue($cache->includeDefaultVary);
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
            groups: ['product:{id}'],
            groupResolver: TestCacheGroupResolver::class,
            includeDefaultVary: false,
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
        self::assertSame(TestResponseMutator::class, $cache->responseMutator,);
        self::assertSame(['product:{id}'], $cache->groups);
        self::assertSame(TestCacheGroupResolver::class, $cache->groupResolver);
        self::assertFalse($cache->includeDefaultVary);
    }

    public function testItSupportsBuiltInAuthVariation(): void
    {
        $cache = new OperationCache(
            ttl: 300,
            varyByAuth: true,
        );

        self::assertTrue($cache->varyByAuth);
    }

    public function testItRejectsEmptyCacheGroup(): void
    {
        $this->expectException(InvalidOperationCacheException::class);
        new OperationCache(ttl: 300, groups: [' ']);
    }

    public function testItRejectsWildcardCacheMembership(): void
    {
        $this->expectException(InvalidOperationCacheException::class);
        new OperationCache(ttl: 300, groups: ['product:*']);
    }

    public function testItRejectsDuplicateCacheGroups(): void
    {
        $this->expectException(InvalidOperationCacheException::class);
        new OperationCache(ttl: 300, groups: ['products', ' products ']);
    }

    public function testItRejectsEmptyCacheGroupResolver(): void
    {
        $this->expectException(InvalidOperationCacheException::class);
        (new \ReflectionClass(OperationCache::class))->newInstanceArgs([
            'ttl' => 300,
            'groupResolver' => '',
        ]);
    }

    public function testItRejectsZeroTtl(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Operation cache TTL must be greater than zero.',
        );

        new OperationCache(ttl: 0);
    }

    public function testItRejectsNegativeTtl(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );

        new OperationCache(ttl: -1);
    }

    public function testItRejectsEmptyVaryHeader(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Vary-by header cannot be empty.',
        );

        new OperationCache(
            ttl: 300,
            varyByHeaders: ['   '],
        );
    }

    public function testItRejectsDuplicateVaryHeadersCaseInsensitively(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Vary-by header "accept-language" is declared more than once.',
        );

        new OperationCache(
            ttl: 300,
            varyByHeaders: [
                'Accept-Language',
                'accept-language',
            ],
        );
    }

    public function testItRejectsEmptyExcludedResponseHeader(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Excluded response header cannot be empty.',
        );

        new OperationCache(
            ttl: 300,
            excludeResponseHeaders: [''],
        );
    }

    public function testItRejectsDuplicateExcludedResponseHeaders(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );

        new OperationCache(
            ttl: 300,
            excludeResponseHeaders: [
                'X-Request-Id',
                'x-request-id',
            ],
        );
    }

    public function testItRejectsEmptyCustomAuthResolver(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Authentication vary resolver cannot be empty.',
        );

        (new \ReflectionClass(OperationCache::class))->newInstanceArgs([
            'ttl' => 300,
            'varyByAuth' => ' ',
        ]);
    }

    public function testItRejectsEmptyVaryResolver(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Vary resolver cannot be empty.',
        );

        (new \ReflectionClass(OperationCache::class))->newInstanceArgs([
            'ttl' => 300,
            'varyByResolver' => '',
        ]);
    }

    public function testItRejectsEmptyCondition(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Cache condition cannot be empty.',
        );

        (new \ReflectionClass(OperationCache::class))->newInstanceArgs([
            'ttl' => 300,
            'when' => ' ',
        ]);
    }

    public function testItRejectsEmptyResponseMutator(): void
    {
        $this->expectException(
            InvalidOperationCacheException::class,
        );
        $this->expectExceptionMessage(
            'Response mutator cannot be empty.',
        );

        (new \ReflectionClass(OperationCache::class))->newInstanceArgs([
            'ttl' => 300,
            'responseMutator' => '',
        ]);
    }
}
