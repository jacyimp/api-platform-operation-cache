<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidatorInterface;
use JacyImp\ApiPlatformOperationCache\Event\CacheGroupsInvalidatedEvent;
use JacyImp\ApiPlatformOperationCache\Event\CacheHitEvent;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelServiceProvider;
use JacyImp\ApiPlatformOperationCache\Laravel\Middleware\ApiPlatformOperationCacheMiddleware;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture\ApiPlatformOperationMiddleware;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture\CountingEndpoint;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class LaravelOperationCacheIntegrationTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders(
        mixed $app,
    ): array {
        return [
            LaravelServiceProvider::class,
        ];
    }

    protected function defineEnvironment(
        mixed $app,
    ): void {
        $config = $app->make(
            Repository::class,
        );

        $config->set(
            'cache.default',
            'array',
        );

        $config->set(
            'cache.stores.array',
            [
                'driver' => 'array',
            ],
        );

        $config->set(
            'api-platform-operation-cache.store',
            'array',
        );
        $config->set(
            'api-platform-operation-cache.vary_by_headers',
            ['X-Currency'],
        );
    }

    protected function defineRoutes(
        mixed $router,
    ): void {
        $this->cachedRoute(
            $router,
            '/response-mutator-products/{id}',
            'response',
        );

        $this->cachedRoute(
            $router,
            '/response-exclusion-products/{id}',
            'response-exclusion',
        );

        $this->cachedRoute(
            $router,
            '/response-default-products/{id}',
            'response-defaults',
        );

        $this->cachedRoute(
            $router,
            '/cached-products/{id}',
            'plain',
        );

        $this->cachedRoute(
            $router,
            '/conditionally-uncached-products/{id}',
            'condition',
        );

        $this->cachedRoute(
            $router,
            '/header-vary-products/{id}',
            'header',
        );
        $this->cachedRoute($router, '/default-vary-products/{id}', 'default-vary');
        $this->cachedRoute($router, '/no-default-vary-products/{id}', 'no-default-vary');

        $this->cachedRoute(
            $router,
            '/auth-vary-products/{id}',
            'auth',
        );

        $this->cachedRoute(
            $router,
            '/resolver-vary-products/{id}',
            'resolver',
        );

        $router
            ->get(
                '/ordinary/{id}',
                CountingEndpoint::class,
            )
            ->middleware(
                ApiPlatformOperationCacheMiddleware::class,
            );

        $router
            ->patch(
                '/cached-products/{id}',
                static fn (): Response => new Response('', Response::HTTP_NO_CONTENT),
            )
            ->middleware([
                ApiPlatformOperationMiddleware::class . ':write',
                ApiPlatformOperationCacheMiddleware::class,
            ]);

        $router
            ->patch(
                '/failed-cached-products/{id}',
                static fn (): Response => new Response('', Response::HTTP_UNPROCESSABLE_ENTITY),
            )
            ->middleware([
                ApiPlatformOperationMiddleware::class . ':failed-write',
                ApiPlatformOperationCacheMiddleware::class,
            ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        CountingEndpoint::reset();

        $this->application()
            ->make(CacheFactory::class)
            ->store('array')
            ->clear();
    }

    #[Test]
    public function secondIdenticalRequestSkipsApplicationHandling(): void
    {
        $this->assertEndpointValue(
            '/cached-products/42',
            'endpoint-call-1',
        );

        $this->assertEndpointValue(
            '/cached-products/42',
            'endpoint-call-1',
        );

        self::assertSame(
            1,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function differentUrisUseDifferentCacheEntries(): void
    {
        $this->assertEndpointValue(
            '/cached-products/42',
            'endpoint-call-1',
        );

        $this->assertEndpointValue(
            '/cached-products/43',
            'endpoint-call-2',
        );

        $this->assertEndpointValue(
            '/cached-products/42',
            'endpoint-call-1',
        );

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function exactPrefixAndGlobalInvalidation(): void
    {
        $this->assertEndpointValue('/cached-products/1', 'endpoint-call-1');
        $this->assertEndpointValue('/cached-products/2', 'endpoint-call-2');
        $invalidator = $this->application()->make(CacheInvalidatorInterface::class);

        $invalidator->invalidateGroups(['product:1']);
        $this->assertEndpointValue('/cached-products/1', 'endpoint-call-3');
        $this->assertEndpointValue('/cached-products/2', 'endpoint-call-2');

        $invalidator->invalidateGroups(['product:*']);
        $this->assertEndpointValue('/cached-products/1', 'endpoint-call-4');
        $this->assertEndpointValue('/cached-products/2', 'endpoint-call-5');

        $invalidator->invalidateGroups(['*']);
        $this->assertEndpointValue('/cached-products/1', 'endpoint-call-6');
        $this->assertEndpointValue('/cached-products/2', 'endpoint-call-7');
    }

    #[Test]
    public function successfulWriteMetadataInvalidatesAndUsesLaravelEventListeners(): void
    {
        $invalidations = [];
        $hits = [];
        $events = $this->application()->make(Dispatcher::class);
        $events->listen(
            CacheGroupsInvalidatedEvent::class,
            static function (CacheGroupsInvalidatedEvent $event) use (&$invalidations): void {
                $invalidations[] = $event;
            },
        );
        $events->listen(CacheHitEvent::class, static function (CacheHitEvent $event) use (&$hits): void {
            $hits[] = $event;
        });

        $this->assertEndpointValue('/cached-products/42', 'endpoint-call-1');
        $this->assertEndpointValue('/cached-products/42', 'endpoint-call-1');
        self::assertCount(1, $hits);

        $this->patchJson('/cached-products/42')->assertNoContent();
        self::assertCount(1, $invalidations);
        self::assertSame(['product:42'], $invalidations[0]->groups);

        $this->assertEndpointValue('/cached-products/42', 'endpoint-call-2');
    }

    #[Test]
    public function unsuccessfulWriteMetadataDoesNotInvalidate(): void
    {
        $this->assertEndpointValue('/cached-products/42', 'endpoint-call-1');
        $this->patchJson('/failed-cached-products/42')->assertUnprocessable();
        $this->assertEndpointValue('/cached-products/42', 'endpoint-call-1');
        self::assertSame(1, CountingEndpoint::$calls);
    }

    #[Test]
    public function differentQueryStringsUseDifferentCacheEntries(): void
    {
        $this->assertEndpointValue(
            '/cached-products/42?view=summary',
            'endpoint-call-1',
        );

        $this->assertEndpointValue(
            '/cached-products/42?view=detail',
            'endpoint-call-2',
        );

        $this->assertEndpointValue(
            '/cached-products/42?view=summary',
            'endpoint-call-1',
        );

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function falseConditionBypassesCache(): void
    {
        $this->assertEndpointValue(
            '/conditionally-uncached-products/42',
            'endpoint-call-1',
        );

        $this->assertEndpointValue(
            '/conditionally-uncached-products/42',
            'endpoint-call-2',
        );

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function varyByHeaderCreatesIndependentCacheEntries(): void
    {
        $this->assertEndpointValue(
            '/header-vary-products/42',
            'endpoint-call-1',
            [
                'Accept-Language' => 'en',
            ],
        );

        $this->assertEndpointValue(
            '/header-vary-products/42',
            'endpoint-call-2',
            [
                'Accept-Language' => 'fr',
            ],
        );

        $this->assertEndpointValue(
            '/header-vary-products/42',
            'endpoint-call-1',
            [
                'Accept-Language' => 'en',
            ],
        );

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function defaultVaryHeadersAndOptOut(): void
    {
        $this->assertEndpointValue('/default-vary-products/42', 'endpoint-call-1', ['X-Currency' => 'USD']);
        $this->assertEndpointValue('/default-vary-products/42', 'endpoint-call-2', ['X-Currency' => 'EUR']);
        $this->assertEndpointValue('/no-default-vary-products/42', 'endpoint-call-3', ['X-Currency' => 'USD']);
        $this->assertEndpointValue('/no-default-vary-products/42', 'endpoint-call-3', ['X-Currency' => 'EUR']);
    }

    #[Test]
    public function varyByAuthCreatesIndependentCacheEntries(): void
    {
        $this->assertEndpointValue(
            '/auth-vary-products/42',
            'endpoint-call-1',
            [
                'X-User' => 'alice',
            ],
        );

        $this->assertEndpointValue(
            '/auth-vary-products/42',
            'endpoint-call-2',
            [
                'X-User' => 'bob',
            ],
        );

        $this->assertEndpointValue(
            '/auth-vary-products/42',
            'endpoint-call-1',
            [
                'X-User' => 'alice',
            ],
        );

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function customVaryResolverCreatesIndependentCacheEntries(): void
    {
        $this->assertEndpointValue(
            '/resolver-vary-products/42',
            'endpoint-call-1',
            [
                'X-Tenant' => 'tenant-a',
            ],
        );

        $this->assertEndpointValue(
            '/resolver-vary-products/42',
            'endpoint-call-2',
            [
                'X-Tenant' => 'tenant-b',
            ],
        );

        $this->assertEndpointValue(
            '/resolver-vary-products/42',
            'endpoint-call-1',
            [
                'X-Tenant' => 'tenant-a',
            ],
        );

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function responseMutatorRunsForCachedCopyAndCacheHit(): void
    {
        $first = $this->getJson(
            '/response-mutator-products/42',
        );

        $first
            ->assertOk()
            ->assertHeaderMissing(
                'X-Cached-Copy',
            )
            ->assertHeaderMissing(
                'X-Cache-Hit',
            );

        $second = $this->getJson(
            '/response-mutator-products/42',
        );

        $second
            ->assertOk()
            ->assertHeader(
                'X-Cached-Copy',
                'yes',
            )
            ->assertHeader(
                'X-Cache-Hit',
                'yes',
            )
            ->assertHeader(
                'X-Excluded',
                'should-not-survive',
            )
            ->assertHeaderMissing('Age')
            ->assertHeaderMissing(
                'Set-Cookie',
            );

        self::assertSame(
            1,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function customResponseHeadersAreExcludedFromCachedResponse(): void
    {
        $this->getJson(
            '/response-exclusion-products/42',
        )->assertOk();

        $this->getJson(
            '/response-exclusion-products/42',
        )
            ->assertOk()
            ->assertHeader(
                'X-Cached-Copy',
                'yes',
            )
            ->assertHeaderMissing(
                'X-Excluded',
            );

        self::assertSame(
            1,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function defaultResponseExclusionsCanBeDisabled(): void
    {
        $this->getJson(
            '/response-default-products/42',
        )->assertOk();

        $this->getJson(
            '/response-default-products/42',
        )
            ->assertOk()
            ->assertHeader(
                'Age',
                '60',
            )
            ->assertHeaderMissing(
                'Set-Cookie',
            );

        self::assertSame(
            1,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function ordinaryLaravelRequestsAreIgnored(): void
    {
        $this->assertEndpointValue(
            '/ordinary/42',
            'endpoint-call-1',
        );

        $this->assertEndpointValue(
            '/ordinary/42',
            'endpoint-call-2',
        );

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function packageMiddlewareIsRegisteredWithApiPlatformDefaults(): void
    {
        $middleware = $this->application()
            ->make(Repository::class)
            ->get(
                'api-platform.defaults.middleware',
                [],
            );

        self::assertIsArray($middleware);

        self::assertContains(
            LaravelServiceProvider::MIDDLEWARE,
            $middleware,
        );

        (new LaravelServiceProvider($this->application()))->register();

        $registeredMiddleware = $this->application()
            ->make(Repository::class)
            ->get('api-platform.defaults.middleware', []);

        self::assertIsArray($registeredMiddleware);
        self::assertCount(
            1,
            array_keys(
                $registeredMiddleware,
                LaravelServiceProvider::MIDDLEWARE,
                true,
            ),
        );

        $this->application()
            ->make(Repository::class)
            ->set('api-platform.defaults.middleware', 'custom-middleware');

        (new LaravelServiceProvider($this->application()))->register();

        self::assertSame(
            [
                'custom-middleware',
                LaravelServiceProvider::MIDDLEWARE,
            ],
            $this->application()
                ->make(Repository::class)
                ->get('api-platform.defaults.middleware'),
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function assertEndpointValue(
        string $uri,
        string $value,
        array $headers = [],
    ): void {
        $this
            ->getJson(
                $uri,
                $headers,
            )
            ->assertOk()
            ->assertJson([
                'value' => $value,
            ]);
    }

    private function cachedRoute(
        Router $router,
        string $uri,
        string $scenario,
    ): void {
        $router
            ->get(
                $uri,
                CountingEndpoint::class,
            )
            ->middleware([
                ApiPlatformOperationMiddleware::class
                . ':'
                . $scenario,
                ApiPlatformOperationCacheMiddleware::class,
            ]);
    }

    private function application(): Application
    {
        return $this->app
            ?? throw new \LogicException(
                'Laravel application is not initialized.',
            );
    }
}
