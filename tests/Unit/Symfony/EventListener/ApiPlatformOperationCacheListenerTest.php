<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\EventListener;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use JacyImp\ApiPlatformOperationCache\Core\ResponseCachePolicy;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Symfony\EventListener\ApiPlatformOperationCacheListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
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

        self::assertNotNull($response);
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

    private function listener(
        ListenerTestCacheStore $store,
    ): ApiPlatformOperationCacheListener {
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
        );
    }
}

final class ListenerTestCacheStore implements CacheStoreInterface
{
    public int $getCalls = 0;

    public int $putCalls = 0;

    public ?CachedResponse $lastResponse = null;

    public ?int $lastTtl = null;

    public function __construct(
        private readonly ?CachedResponse $response = null,
    ) {
    }

    public function get(string $key): ?CachedResponse
    {
        ++$this->getCalls;

        return $this->response;
    }

    public function put(
        string $key,
        CachedResponse $response,
        int $ttl,
    ): void {
        ++$this->putCalls;

        $this->lastResponse = $response;
        $this->lastTtl = $ttl;
    }
}

final class ListenerTestAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}

final class ListenerTestContainer implements ContainerInterface
{
    public function get(string $id): object
    {
        throw new \LogicException(sprintf(
            'Unexpected strategy "%s".',
            $id,
        ));
    }

    public function has(string $id): bool
    {
        return false;
    }
}
