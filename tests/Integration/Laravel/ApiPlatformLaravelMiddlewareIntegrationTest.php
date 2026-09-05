<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation\Factory\OperationMetadataFactory;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelServiceProvider;
use JacyImp\ApiPlatformOperationCache\Laravel\Middleware\ApiPlatformOperationCacheMiddleware;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture\CountingEndpoint;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ApiPlatformLaravelMiddlewareIntegrationTest extends TestCase
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
    public function itRunsAfterApiPlatformPopulatesTheOperation(): void
    {
        $apiPlatformMiddleware =
            'ApiPlatform\\Laravel\\ApiPlatformMiddleware';

        if (!class_exists($apiPlatformMiddleware)) {
            self::markTestSkipped(
                'api-platform/laravel is not installed.',
            );
        }

        $operationName = 'cached_product';

        $operation = new Get(
            name: $operationName,
            uriTemplate: '/api-platform-cached/{id}',
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                ),
            ],
        );

        $resourceClass = self::class;

        $resourceNames = self::createStub(
            ResourceNameCollectionFactoryInterface::class,
        );

        $resourceNames
            ->method('create')
            ->willReturn(
                new ResourceNameCollection([
                    $resourceClass,
                ]),
            );

        $resourceMetadata = self::createStub(
            ResourceMetadataCollectionFactoryInterface::class,
        );

        $resourceMetadata
            ->method('create')
            ->willReturn(
                new ResourceMetadataCollection(
                    $resourceClass,
                    [
                        new ApiResource(
                            operations: [
                                $operation,
                            ],
                        ),
                    ],
                ),
            );

        $this->application()->instance(
            OperationMetadataFactory::class,
            new OperationMetadataFactory(
                $resourceNames,
                $resourceMetadata,
            ),
        );

        $this->application()
            ->make(Router::class)
            ->get(
                '/api-platform-cached/{id}',
                CountingEndpoint::class,
            )
            ->middleware([
                $apiPlatformMiddleware
                . ':'
                . $operationName,
                ApiPlatformOperationCacheMiddleware::class,
            ]);

        $this->getJson(
            '/api-platform-cached/42',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-1',
            ]);

        $this->getJson(
            '/api-platform-cached/42',
        )
            ->assertOk()
            ->assertJson([
                'value' => 'endpoint-call-1',
            ]);

        self::assertSame(
            1,
            CountingEndpoint::$calls,
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
