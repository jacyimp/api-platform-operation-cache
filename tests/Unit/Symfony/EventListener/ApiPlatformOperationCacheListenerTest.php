<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\EventListener;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use JacyImp\ApiPlatformOperationCache\Core\ResponseCachePolicy;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Symfony\EventListener\ApiPlatformOperationCacheListener;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\EventListener\Fixture\ListenerTestAuthIdentityResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\EventListener\Fixture\ListenerTestCacheStore;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\EventListener\Fixture\ListenerTestContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ApiPlatformOperationCacheListener::class)]
final class ApiPlatformOperationCacheListenerTest extends TestCase
{
    public function testItIgnoresSubRequests(): void
    {
        $store = new ListenerTestCacheStore();
        $listener = $this->listener($store);

        $request = $this->requestWithCachedOperation();

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        $listener->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
        self::assertSame(0, $store->getCalls);
    }

    public function testItIgnoresNonApiPlatformRequest(): void
    {
        $store = new ListenerTestCacheStore();
        $listener = $this->listener($store);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/regular-route'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
        self::assertSame(0, $store->getCalls);
    }

    public function testCacheHitSetsKernelResponse(): void
    {
        $store = new ListenerTestCacheStore(
            new CachedResponse(
                content: '{"cached":true}',
                statusCode: Response::HTTP_OK,
                headers: [
                    'content-type' => [
                        'application/json',
                    ],
                ],
            ),
        );

        $listener = $this->listener($store);
        $request = $this->requestWithCachedOperation();

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($event);

        self::assertTrue($event->hasResponse());

        $response = $event->getResponse();

        self::assertSame(
            '{"cached":true}',
            $response->getContent(),
        );
    }

    public function testCacheMissIsStoredOnKernelResponse(): void
    {
        $store = new ListenerTestCacheStore();
        $listener = $this->listener($store);

        $request = $this->requestWithCachedOperation();

        $requestEvent = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($requestEvent);

        self::assertFalse($requestEvent->hasResponse());
        self::assertSame(1, $store->getCalls);

        $response = new Response(
            '{"fresh":true}',
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/json',
            ],
        );

        $responseEvent = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $listener->onKernelResponse($responseEvent);

        self::assertSame(1, $store->putCalls);
        self::assertSame(300, $store->lastTtl);
        self::assertNotNull($store->lastResponse);
        self::assertSame(
            '{"fresh":true}',
            $store->lastResponse->content,
        );
    }

    public function testCacheHitIsNotStoredAgainOnKernelResponse(): void
    {
        $store = new ListenerTestCacheStore(
            new CachedResponse(
                content: '{"cached":true}',
                statusCode: Response::HTTP_OK,
                headers: [],
            ),
        );

        $listener = $this->listener($store);
        $request = $this->requestWithCachedOperation();

        $requestEvent = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($requestEvent);

        $response = $requestEvent->getResponse();

        self::assertNotNull($response);

        $responseEvent = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $listener->onKernelResponse($responseEvent);

        self::assertSame(0, $store->putCalls);
    }

    public function testCacheContextIsConsumedAfterResponse(): void
    {
        $store = new ListenerTestCacheStore();
        $listener = $this->listener($store);
        $request = $this->requestWithCachedOperation();

        $requestEvent = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onKernelRequest($requestEvent);

        $responseEvent = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response('{}'),
        );

        $listener->onKernelResponse($responseEvent);
        $listener->onKernelResponse($responseEvent);

        self::assertSame(1, $store->putCalls);
    }

    public function testItIgnoresSubResponses(): void
    {
        $store = new ListenerTestCacheStore();
        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $this->requestWithCachedOperation(), HttpKernelInterface::SUB_REQUEST, new Response('{}'),);
        $this->listener($store)->onKernelResponse($event);
        self::assertSame(0, $store->putCalls);
    }

    public function testItResolvesOperationFromResourceMetadata(): void
    {
        $operation = new Get(name: 'products');
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class,);
        $factory->method('create')->with('App\\Product')->willReturn(new ResourceMetadataCollection('App\\Product', [new ApiResource(operations: [$operation])],),);
        $request = Request::create('/products');
        $request->attributes->set('_api_resource_class', 'App\\Product');
        $request->attributes->set('_api_operation_name', 'products');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST,);
        $this->listener(new ListenerTestCacheStore(), $factory)
            ->onKernelRequest($event);
        self::assertInstanceOf(Get::class, $request->attributes->get('_api_operation'),);
    }
    private function requestWithCachedOperation(): Request
    {
        $request = Request::create('/products');

        $request->attributes->set(
            '_api_operation',
            new Get(
                name: 'get_products',
                extraProperties: [
                    OperationCache::class => new OperationCache(
                        ttl: 300,
                    ),
                ],
            ),
        );

        return $request;
    }

    private function listener(ListenerTestCacheStore $store, ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,): ApiPlatformOperationCacheListener
    {
        $registry = new CacheStrategyRegistry(
            new ListenerTestContainer(),
        );

        $evaluator = new OperationCacheEvaluator(
            new ListenerTestAuthIdentityResolver(),
            $registry,
        );

        return new ApiPlatformOperationCacheListener(
            new OperationCacheHandler(
                metadataExtractor: new OperationCacheMetadataExtractor(),
                cachePolicy: new ResponseCachePolicy(),
                keyGenerator: new CacheKeyGenerator($evaluator),
                cacheStore: $store,
                responseFactory: new CachedResponseFactory(
                    $registry,
                ),
            ),
            $resourceMetadataCollectionFactory,
        );
    }
}
