<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel\Middleware;

use ApiPlatform\Metadata\Get;
use Illuminate\Http\Request;
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
use JacyImp\ApiPlatformOperationCache\Laravel\Middleware\ApiPlatformOperationCacheMiddleware;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ApiPlatformOperationCacheMiddleware::class)]
final class ApiPlatformOperationCacheMiddlewareTest extends TestCase
{
    public function testItIgnoresNonApiPlatformRequest(): void
    {
        $store = new MiddlewareTestCacheStore();
        $middleware = $this->middleware($store);

        $nextCalls = 0;

        $response = $middleware->handle(
            Request::create('/regular-route'),
            static function (
                Request $request,
            ) use (&$nextCalls): Response {
                ++$nextCalls;

                return new Response('fresh');
            },
        );

        self::assertSame(1, $nextCalls);
        self::assertSame('fresh', $response->getContent());
        self::assertSame(0, $store->getCalls);
        self::assertSame(0, $store->putCalls);
    }

    public function testCacheHitSkipsApplicationPipeline(): void
    {
        $store = new MiddlewareTestCacheStore(
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

        $middleware = $this->middleware($store);
        $request = $this->cachedRequest();

        $nextCalls = 0;

        $response = $middleware->handle(
            $request,
            static function (
                Request $request,
            ) use (&$nextCalls): Response {
                ++$nextCalls;

                return new Response('fresh');
            },
        );

        self::assertSame(0, $nextCalls);
        self::assertSame(1, $store->getCalls);
        self::assertSame(0, $store->putCalls);
        self::assertSame(
            '{"cached":true}',
            $response->getContent(),
        );
    }

    public function testCacheMissStoresApplicationResponse(): void
    {
        $store = new MiddlewareTestCacheStore();
        $middleware = $this->middleware($store);

        $response = $middleware->handle(
            $this->cachedRequest(),
            static fn (
                Request $request,
            ): Response => new Response(
                '{"fresh":true}',
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        );

        self::assertSame(
            '{"fresh":true}',
            $response->getContent(),
        );

        self::assertSame(1, $store->getCalls);
        self::assertSame(1, $store->putCalls);
        self::assertSame(300, $store->lastTtl);

        self::assertNotNull(
            $store->lastResponse,
        );

        self::assertSame(
            '{"fresh":true}',
            $store->lastResponse->content,
        );
    }

    public function testUncacheableResponseIsNotStored(): void
    {
        $store = new MiddlewareTestCacheStore();
        $middleware = $this->middleware($store);

        $middleware->handle(
            $this->cachedRequest(),
            static fn (
                Request $request,
            ): Response => new Response(
                '{"error":true}',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ),
        );

        self::assertSame(1, $store->getCalls);
        self::assertSame(0, $store->putCalls);
    }

    private function cachedRequest(): Request
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

    private function middleware(
        MiddlewareTestCacheStore $store,
    ): ApiPlatformOperationCacheMiddleware {
        $registry = new CacheStrategyRegistry(
            new MiddlewareTestContainer(),
        );

        return new ApiPlatformOperationCacheMiddleware(
            new OperationCacheHandler(
                metadataExtractor: new OperationCacheMetadataExtractor(),
                cachePolicy: new ResponseCachePolicy(),
                keyGenerator: new CacheKeyGenerator(
                    new OperationCacheEvaluator(
                        new MiddlewareTestAuthResolver(),
                        $registry,
                    ),
                ),
                cacheStore: $store,
                responseFactory: new CachedResponseFactory(
                    $registry,
                ),
            ),
        );
    }
}

final class MiddlewareTestCacheStore implements CacheStoreInterface
{
    public int $getCalls = 0;

    public int $putCalls = 0;

    public ?CachedResponse $lastResponse = null;

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

        $this->lastResponse = $response;
        $this->lastTtl = $ttl;
    }
}

final class MiddlewareTestAuthResolver implements AuthIdentityResolverInterface
{
    public function resolve(
        SymfonyRequest $request,
    ): ?string {
        return null;
    }
}

final class MiddlewareTestContainer implements ContainerInterface
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
