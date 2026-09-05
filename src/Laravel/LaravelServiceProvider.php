<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Laravel;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use JacyImp\ApiPlatformOperationCache\Core\ResponseCachePolicy;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use JacyImp\ApiPlatformOperationCache\Laravel\Middleware\ApiPlatformOperationCacheMiddleware;

final class LaravelServiceProvider extends ServiceProvider
{
    public const MIDDLEWARE = 'api-platform-operation-cache';

    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2)
            . '/config/api-platform-operation-cache.php',
            'api-platform-operation-cache',
        );

        $this->registerApiPlatformMiddleware();

        $this->app->singleton(
            OperationCacheMetadataExtractor::class,
        );

        $this->app->singleton(
            ResponseCachePolicy::class,
        );

        $this->app->singleton(
            LaravelCacheStrategyLocator::class,
            static fn (
                Application $app,
            ): LaravelCacheStrategyLocator => new LaravelCacheStrategyLocator(
                $app,
            ),
        );

        $this->app->singleton(
            CacheStrategyRegistry::class,
            static fn (
                Application $app,
            ): CacheStrategyRegistry => new CacheStrategyRegistry(
                $app->make(
                    LaravelCacheStrategyLocator::class,
                ),
            ),
        );

        $this->app->singleton(
            LaravelAuthIdentityResolver::class,
        );

        $this->app->alias(
            LaravelAuthIdentityResolver::class,
            AuthIdentityResolverInterface::class,
        );

        $this->app->singleton(
            OperationCacheEvaluator::class,
            static fn (
                Application $app,
            ): OperationCacheEvaluator => new OperationCacheEvaluator(
                $app->make(
                    AuthIdentityResolverInterface::class,
                ),
                $app->make(
                    CacheStrategyRegistry::class,
                ),
            ),
        );

        $this->app->singleton(
            CacheKeyGenerator::class,
            static fn (
                Application $app,
            ): CacheKeyGenerator => new CacheKeyGenerator(
                $app->make(
                    OperationCacheEvaluator::class,
                ),
            ),
        );

        $this->app->singleton(
            CachedResponseFactory::class,
            static fn (
                Application $app,
            ): CachedResponseFactory => new CachedResponseFactory(
                $app->make(
                    CacheStrategyRegistry::class,
                ),
            ),
        );

        $this->app->singleton(
            CacheStoreInterface::class,
            static function (
                Application $app,
            ): CacheStoreInterface {
                $config = $app->make(
                    ConfigRepository::class,
                );

                $store = $config->get(
                    'api-platform-operation-cache.store',
                );

                $repository = $app
                    ->make(CacheManager::class)
                    ->store(
                        is_string($store) && trim($store) !== ''
                            ? $store
                            : null,
                    );

                return new LaravelCacheStore(
                    $repository,
                );
            },
        );

        $this->app->singleton(
            OperationCacheHandler::class,
            static fn (
                Application $app,
            ): OperationCacheHandler => new OperationCacheHandler(
                metadataExtractor: $app->make(
                    OperationCacheMetadataExtractor::class,
                ),
                cachePolicy: $app->make(
                    ResponseCachePolicy::class,
                ),
                keyGenerator: $app->make(
                    CacheKeyGenerator::class,
                ),
                cacheStore: $app->make(
                    CacheStoreInterface::class,
                ),
                responseFactory: $app->make(
                    CachedResponseFactory::class,
                ),
            ),
        );

        $this->app->singleton(
            ApiPlatformOperationCacheMiddleware::class,
        );
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware(
            self::MIDDLEWARE,
            ApiPlatformOperationCacheMiddleware::class,
        );

        $this->publishes(
            [
                dirname(__DIR__, 2)
                . '/config/api-platform-operation-cache.php'
                => $this->app->configPath(
                    'api-platform-operation-cache.php',
                ),
            ],
            'api-platform-operation-cache-config',
        );
    }

    private function registerApiPlatformMiddleware(): void
    {
        $config = $this->app->make(
            ConfigRepository::class,
        );

        $middleware = $config->get(
            'api-platform.defaults.middleware',
            [],
        );

        $middleware = is_array($middleware)
            ? $middleware
            : [$middleware];

        if (
            !in_array(
                self::MIDDLEWARE,
                $middleware,
                true,
            )
        ) {
            $middleware[] = self::MIDDLEWARE;
        }

        $config->set(
            'api-platform.defaults.middleware',
            $middleware,
        );
    }
}
