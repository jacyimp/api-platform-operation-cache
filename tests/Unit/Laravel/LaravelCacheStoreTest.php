<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel;

use Illuminate\Contracts\Cache\Repository;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelCacheStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelCacheStore::class)]
final class LaravelCacheStoreTest extends TestCase
{
    public function testItReturnsNullOnCacheMiss(): void
    {
        $cache = $this->createMock(Repository::class);
        $cache
            ->method('get')
            ->with('key')
            ->willReturn(null);

        $store = new LaravelCacheStore($cache);

        self::assertNull($store->get('key'));
    }

    public function testItReturnsCachedResponse(): void
    {
        $response = new CachedResponse(
            content: '{}',
            statusCode: 200,
            headers: [],
        );

        $cache = $this->createMock(Repository::class);
        $cache
            ->method('get')
            ->with('key')
            ->willReturn($response);

        $store = new LaravelCacheStore($cache);

        self::assertSame(
            $response,
            $store->get('key'),
        );
    }

    public function testItTreatsUnexpectedCachedValueAsMiss(): void
    {
        $cache = $this->createMock(Repository::class);
        $cache
            ->method('get')
            ->with('key')
            ->willReturn('invalid');

        $store = new LaravelCacheStore($cache);

        self::assertNull($store->get('key'));
    }

    public function testItStoresResponseWithTtl(): void
    {
        $response = new CachedResponse(
            content: '{}',
            statusCode: 200,
            headers: [],
        );

        $cache = $this->createMock(Repository::class);

        $cache
            ->expects(self::once())
            ->method('put')
            ->with(
                'key',
                $response,
                300,
            );

        $store = new LaravelCacheStore($cache);

        $store->put(
            'key',
            $response,
            300,
        );
    }
}
