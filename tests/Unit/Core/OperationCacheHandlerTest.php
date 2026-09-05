<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheLookup;
use JacyImp\ApiPlatformOperationCache\Core\ResponseCachePolicy;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(OperationCacheHandler::class)]
#[CoversClass(OperationCacheLookup::class)]
final class OperationCacheHandlerTest extends TestCase
{
    public function testItBypassesOperationWithoutCacheMetadata(): void
    {
        $store = new HandlerTestCacheStore();
        $handler = $this->handler($store);

        $lookup = $handler->lookup(
            new Get(),
            Request::create('/products'),
        );

        self::assertFalse($lookup->isHit());
        self::assertFalse($lookup->shouldStore());
        self::assertNull($lookup->response);
        self::assertNull($lookup->context);
        self::assertSame(0, $store->getCalls);
    }

    public function testItBypassesNonCacheableRequestMethod(): void
    {
        $store = new HandlerTestCacheStore();
        $handler = $this->handler($store);

        $lookup = $handler->lookup(
            $this->cachedOperation(),
            Request::create(
                '/products',
                Request::METHOD_POST,
            ),
        );

        self::assertFalse($lookup->isHit());
        self::assertFalse($lookup->shouldStore());
        self::assertSame(0, $store->getCalls);
    }

    public function testItBypassesWhenConditionDoesNotMatch(): void
    {
        $condition = new HandlerNeverCacheCondition();

        $store = new HandlerTestCacheStore();
        $handler = $this->handler(
            $store,
            [
                HandlerNeverCacheCondition::class => $condition,
            ],
        );

        $operation = new Get(
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                    when: HandlerNeverCacheCondition::class,
                ),
            ],
        );

        $lookup = $handler->lookup(
            $operation,
            Request::create('/products'),
        );

        self::assertFalse($lookup->isHit());
        self::assertFalse($lookup->shouldStore());
        self::assertSame(0, $store->getCalls);
    }

    public function testCacheMissReturnsStorageContext(): void
    {
        $store = new HandlerTestCacheStore();
        $handler = $this->handler($store);

        $lookup = $handler->lookup(
            $this->cachedOperation(),
            Request::create('/products?page=2'),
        );

        self::assertFalse($lookup->isHit());
        self::assertTrue($lookup->shouldStore());
        self::assertNull($lookup->response);
        self::assertNotNull($lookup->context);
        self::assertSame(300, $lookup->context->cache->ttl);
        self::assertNotSame('', $lookup->context->key);
        self::assertSame(1, $store->getCalls);
    }

    public function testCacheHitReturnsRestoredResponse(): void
    {
        $store = new HandlerTestCacheStore(
            cached: new CachedResponse(
                content: '{"cached":true}',
                statusCode: Response::HTTP_OK,
                headers: [
                    'content-type' => [
                        'application/json',
                    ],
                ],
            ),
        );

        $handler = $this->handler($store);

        $lookup = $handler->lookup(
            $this->cachedOperation(),
            Request::create('/products'),
        );

        self::assertTrue($lookup->isHit());
        self::assertFalse($lookup->shouldStore());
        self::assertNotNull($lookup->response);
        self::assertSame(
            '{"cached":true}',
            $lookup->response->getContent(),
        );
        self::assertSame(
            'application/json',
            $lookup->response->headers->get('Content-Type'),
        );
    }

    public function testItStoresCacheableResponseWithConfiguredTtl(): void
    {
        $store = new HandlerTestCacheStore();
        $handler = $this->handler($store);

        $lookup = $handler->lookup(
            $this->cachedOperation(),
            Request::create('/products'),
        );

        self::assertNotNull($lookup->context);

        $handler->store(
            $lookup->context,
            Request::create('/products'),
            new Response(
                '{"fresh":true}',
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        );

        self::assertSame(1, $store->putCalls);
        self::assertSame(300, $store->lastTtl);
        self::assertNotNull($store->lastCached);
        self::assertSame(
            '{"fresh":true}',
            $store->lastCached->content,
        );
    }

    public function testItDoesNotStoreUncacheableResponse(): void
    {
        $store = new HandlerTestCacheStore();
        $handler = $this->handler($store);

        $lookup = $handler->lookup(
            $this->cachedOperation(),
            Request::create('/products'),
        );

        self::assertNotNull($lookup->context);

        $handler->store(
            $lookup->context,
            Request::create('/products'),
            new Response(
                '{"error":true}',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ),
        );

        self::assertSame(0, $store->putCalls);
    }

    public function testConditionIsNotReevaluatedWhenResponseIsStored(): void
    {
        $condition = new HandlerCountingCondition();

        $store = new HandlerTestCacheStore();
        $handler = $this->handler(
            $store,
            [
                HandlerCountingCondition::class => $condition,
            ],
        );

        $operation = new Get(
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                    when: HandlerCountingCondition::class,
                ),
            ],
        );

        $request = Request::create('/products');

        $lookup = $handler->lookup(
            $operation,
            $request,
        );

        self::assertNotNull($lookup->context);
        self::assertSame(1, $condition->calls);

        $handler->store(
            $lookup->context,
            $request,
            new Response('{}'),
        );

        self::assertSame(1, $condition->calls);
    }

    private function cachedOperation(): Get
    {
        return new Get(
            name: 'get_products',
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                ),
            ],
        );
    }

    /**
     * @param array<string, object> $services
     */
    private function handler(
        HandlerTestCacheStore $store,
        array $services = [],
    ): OperationCacheHandler {
        $registry = new CacheStrategyRegistry(
            new HandlerTestContainer($services),
        );

        $evaluator = new OperationCacheEvaluator(
            new HandlerAnonymousAuthResolver(),
            $registry,
        );

        return new OperationCacheHandler(
            metadataExtractor: new OperationCacheMetadataExtractor(),
            cachePolicy: new ResponseCachePolicy(),
            keyGenerator: new CacheKeyGenerator($evaluator),
            cacheStore: $store,
            responseFactory: new CachedResponseFactory($registry),
        );
    }
}

final class HandlerTestCacheStore implements CacheStoreInterface
{
    public int $getCalls = 0;

    public int $putCalls = 0;

    public ?CachedResponse $lastCached = null;

    public ?int $lastTtl = null;

    public function __construct(
        private readonly ?CachedResponse $cached = null,
    ) {
    }

    public function get(string $key): ?CachedResponse
    {
        ++$this->getCalls;

        return $this->cached;
    }

    public function put(
        string $key,
        CachedResponse $response,
        int $ttl,
    ): void {
        ++$this->putCalls;

        $this->lastCached = $response;
        $this->lastTtl = $ttl;
    }
}

final class HandlerAnonymousAuthResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}

final class HandlerNeverCacheCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return false;
    }
}

final class HandlerCountingCondition implements CacheConditionInterface
{
    public int $calls = 0;

    public function matches(Request $request): bool
    {
        ++$this->calls;

        return true;
    }
}

final readonly class HandlerTestContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
