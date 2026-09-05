<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelServiceProvider;
use JacyImp\ApiPlatformOperationCache\Laravel\Middleware\ApiPlatformOperationCacheMiddleware;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture\ApiPlatformOperationMiddleware;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture\CountingEndpoint;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
    }

    protected function defineRoutes(
        mixed $router,
    ): void {
        $router
            ->get(
                '/cached-products/{id}',
                CountingEndpoint::class,
            )
            ->middleware([
                ApiPlatformOperationMiddleware::class,
                ApiPlatformOperationCacheMiddleware::class,
            ]);

        $router
            ->get(
                '/ordinary/{id}',
                CountingEndpoint::class,
            )
            ->middleware(
                ApiPlatformOperationCacheMiddleware::class,
            );
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
        $first = $this->getJson(
            '/cached-products/42',
        );

        $first
            ->assertOk()
            ->assertJson([
                'id' => '42',
                'value' => 'endpoint-call-1',
            ]);

        self::assertSame(
            1,
            CountingEndpoint::$calls,
        );

        $second = $this->getJson(
            '/cached-products/42',
        );

        $second
            ->assertOk()
            ->assertJson([
                'id' => '42',
                'value' => 'endpoint-call-1',
            ]);

        self::assertSame(
            1,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function differentUrisUseDifferentCacheEntries(): void
    {
        $this->getJson(
            '/cached-products/42',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-1',
            ]);

        $this->getJson(
            '/cached-products/43',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-2',
            ]);

        $this->getJson(
            '/cached-products/42',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-1',
            ]);

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function differentQueryStringsUseDifferentCacheEntries(): void
    {
        $this->getJson(
            '/cached-products/42?view=summary',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-1',
            ]);

        $this->getJson(
            '/cached-products/42?view=detail',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-2',
            ]);

        $this->getJson(
            '/cached-products/42?view=summary',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-1',
            ]);

        self::assertSame(
            2,
            CountingEndpoint::$calls,
        );
    }

    #[Test]
    public function ordinaryLaravelRequestsAreIgnored(): void
    {
        $this->getJson(
            '/ordinary/42',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-1',
            ]);

        $this->getJson(
            '/ordinary/42',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-2',
            ]);

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
    }

    private function application(): Application
    {
        return $this->app
            ?? throw new \LogicException(
                'Laravel application is not initialized.',
            );
    }
}
