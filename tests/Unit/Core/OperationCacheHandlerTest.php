<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupGenerationManager;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupNormalizer;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupResolver;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheLookup;
use JacyImp\ApiPlatformOperationCache\Core\ResponseCachePolicy;
use JacyImp\ApiPlatformOperationCache\Event\CacheHitEvent;
use JacyImp\ApiPlatformOperationCache\Event\CacheMissEvent;
use JacyImp\ApiPlatformOperationCache\Event\CacheStoredEvent;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\HandlerAnonymousAuthResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\HandlerCountingCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\HandlerNeverCacheCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\HandlerTestCacheStore;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\HandlerTestContainer;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\RecordingEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
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
        $dispatcher = new RecordingEventDispatcher();

        $store = new HandlerTestCacheStore();
        $handler = $this->handler(
            $store,
            [
                HandlerNeverCacheCondition::class => $condition,
            ],
            $dispatcher,
        );

        $operation = new Get(
            extraProperties: [
                new OperationCache(
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
        self::assertSame([], $dispatcher->events);
    }

    public function testCacheMissReturnsStorageContext(): void
    {
        $store = new HandlerTestCacheStore();
        $dispatcher = new RecordingEventDispatcher();
        $handler = $this->handler($store, eventDispatcher: $dispatcher);
        $request = Request::create('/products?page=2');
        $operation = $this->cachedOperation();

        $lookup = $handler->lookup(
            $operation,
            $request,
        );

        self::assertFalse($lookup->isHit());
        self::assertTrue($lookup->shouldStore());
        self::assertNull($lookup->response);
        self::assertNotNull($lookup->context);
        self::assertSame(300, $lookup->context->cache->ttl);
        self::assertNotSame('', $lookup->context->key);
        self::assertSame(1, $store->getCalls);
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(CacheMissEvent::class, $dispatcher->events[0]);
        self::assertSame($operation, $dispatcher->events[0]->operation);
        self::assertSame($request, $dispatcher->events[0]->request);
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

        $dispatcher = new RecordingEventDispatcher();
        $handler = $this->handler($store, eventDispatcher: $dispatcher);
        $request = Request::create('/products');
        $operation = $this->cachedOperation();

        $lookup = $handler->lookup(
            $operation,
            $request,
        );

        self::assertTrue($lookup->isHit());
        self::assertFalse($lookup->shouldStore());
        self::assertNotNull($lookup->response);
        self::assertSame(
            '{"cached":true}',
            $lookup->response->getContent(),
        );
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(CacheHitEvent::class, $dispatcher->events[0]);
        self::assertSame($operation, $dispatcher->events[0]->operation);
        self::assertSame($request, $dispatcher->events[0]->request);
        self::assertSame($lookup->response, $dispatcher->events[0]->response);
        self::assertSame(
            'application/json',
            $lookup->response->headers->get('Content-Type'),
        );
    }

    public function testItStoresCacheableResponseWithConfiguredTtl(): void
    {
        $store = new HandlerTestCacheStore();
        $dispatcher = new RecordingEventDispatcher();
        $handler = $this->handler($store, eventDispatcher: $dispatcher);
        $request = Request::create('/products');
        $response = new Response(
            '{"fresh":true}',
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/json',
            ],
        );

        $lookup = $handler->lookup(
            $this->cachedOperation(),
            Request::create('/products'),
        );

        self::assertNotNull($lookup->context);

        $handler->store(
            $lookup->context,
            $request,
            $response,
        );

        self::assertSame(1, $store->putCalls);
        self::assertSame(300, $store->lastTtl);
        self::assertNotNull($store->lastCached);
        self::assertSame(
            '{"fresh":true}',
            $store->lastCached->content,
        );
        self::assertInstanceOf(CacheMissEvent::class, $dispatcher->events[0]);
        self::assertInstanceOf(CacheStoredEvent::class, $dispatcher->events[1]);
        self::assertSame(300, $dispatcher->events[1]->ttl);
        self::assertSame($lookup->context->operation, $dispatcher->events[1]->operation);
        self::assertSame($request, $dispatcher->events[1]->request);
        self::assertSame($response, $dispatcher->events[1]->response);
    }

    public function testItDoesNotStoreUncacheableResponse(): void
    {
        $store = new HandlerTestCacheStore();
        $dispatcher = new RecordingEventDispatcher();
        $handler = $this->handler($store, eventDispatcher: $dispatcher);

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
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(CacheMissEvent::class, $dispatcher->events[0]);
    }

    public function testFailedCacheWriteDoesNotDispatchStoredEvent(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $handler = $this->handler(
            new HandlerTestCacheStore(throwOnPut: true),
            eventDispatcher: $dispatcher,
        );
        $lookup = $handler->lookup($this->cachedOperation(), Request::create('/products'));
        self::assertNotNull($lookup->context);

        try {
            $handler->store($lookup->context, Request::create('/products'), new Response('{}'));
            self::fail('The cache write should fail.');
        } catch (\RuntimeException) {
            self::assertCount(1, $dispatcher->events);
            self::assertInstanceOf(CacheMissEvent::class, $dispatcher->events[0]);
        }
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
                new OperationCache(
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
                new OperationCache(
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
        EventDispatcherInterface $eventDispatcher = new RecordingEventDispatcher(),
    ): OperationCacheHandler {
        $registry = new CacheStrategyRegistry(
            new HandlerTestContainer($services),
        );

        $evaluator = new OperationCacheEvaluator(
            new HandlerAnonymousAuthResolver(),
            $registry,
        );
        $normalizer = new CacheGroupNormalizer();
        return new OperationCacheHandler(
            metadataExtractor: new OperationCacheMetadataExtractor(),
            cachePolicy: new ResponseCachePolicy(),
            keyGenerator: new CacheKeyGenerator(
                $evaluator,
                new CacheGroupResolver($normalizer, $registry),
                new CacheGroupGenerationManager($store),
            ),
            cacheStore: $store,
            responseFactory: new CachedResponseFactory($registry),
            eventDispatcher: $eventDispatcher,
        );
    }
}
