<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony;

use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Symfony\SymfonyCacheStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(SymfonyCacheStore::class)]
final class SymfonyCacheStoreTest extends TestCase
{
    public function testItReturnsNullOnCacheMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->method('isHit')
            ->willReturn(false);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->with('key')
            ->willReturn($item);

        $store = new SymfonyCacheStore($pool);

        self::assertNull($store->get('key'));
    }

    public function testItReturnsCachedResponse(): void
    {
        $response = new CachedResponse(
            content: '{}',
            statusCode: 200,
            headers: [],
        );

        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->method('isHit')
            ->willReturn(true);
        $item
            ->method('get')
            ->willReturn($response);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->with('key')
            ->willReturn($item);

        $store = new SymfonyCacheStore($pool);

        self::assertSame(
            $response,
            $store->get('key'),
        );
    }

    public function testItTreatsUnexpectedCachedValueAsMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->method('isHit')
            ->willReturn(true);
        $item
            ->method('get')
            ->willReturn('invalid');

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->with('key')
            ->willReturn($item);

        $pool
            ->expects(self::once())
            ->method('deleteItem')
            ->with('key');

        $store = new SymfonyCacheStore($pool);

        self::assertNull($store->get('key'));
    }

    public function testItStoresResponseWithTtl(): void
    {
        $response = new CachedResponse(
            content: '{}',
            statusCode: 200,
            headers: [],
        );

        $item = $this->createMock(CacheItemInterface::class);

        $item
            ->expects(self::once())
            ->method('set')
            ->with($response)
            ->willReturnSelf();

        $item
            ->expects(self::once())
            ->method('expiresAfter')
            ->with(300)
            ->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->with('key')
            ->willReturn($item);

        $pool
            ->expects(self::once())
            ->method('save')
            ->with($item);

        $store = new SymfonyCacheStore($pool);

        $store->put(
            'key',
            $response,
            300,
        );
    }

    public function testItReadsHitStringGenerations(): void
    {
        $hit = $this->createMock(CacheItemInterface::class);
        $hit->method('isHit')->willReturn(true);
        $hit->method('get')->willReturn('generation');
        $hit->method('getKey')->willReturn('hit');
        $miss = $this->createMock(CacheItemInterface::class);
        $miss->method('isHit')->willReturn(false);
        $invalid = $this->createMock(CacheItemInterface::class);
        $invalid->method('isHit')->willReturn(true);
        $invalid->method('get')->willReturn(42);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->willReturn([new \stdClass(), $hit, $miss, $invalid]);

        self::assertSame(
            ['hit' => 'generation'],
            (new SymfonyCacheStore($pool))->getGenerations(['hit', 'miss', 'invalid']),
        );
    }

    public function testItStoresGenerationWithoutExpiry(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('set')->with('generation')->willReturnSelf();
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->with('key')->willReturn($item);
        $pool->expects(self::once())->method('save')->with($item);

        (new SymfonyCacheStore($pool))->putGeneration('key', 'generation');
    }
}
