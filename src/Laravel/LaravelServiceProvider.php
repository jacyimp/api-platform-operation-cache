<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Laravel;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidatorInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupGenerationManager;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupNormalizer;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupResolver;
use JacyImp\ApiPlatformOperationCache\Core\CacheInvalidator;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheInvalidator;
use JacyImp\ApiPlatformOperationCache\Core\ResponseCachePolicy;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use JacyImp\ApiPlatformOperationCache\Laravel\Middleware\ApiPlatformOperationCacheMiddleware;
use Psr\EventDispatcher\EventDispatcherInterface;

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
            LaravelEventDispatcher::class,
            static fn (Application $app): LaravelEventDispatcher => new LaravelEventDispatcher(
                $app->make(Dispatcher::class),
            ),
        );
        $this->app->alias(
            LaravelEventDispatcher::class,
            EventDispatcherInterface::class,
        );

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
                $app->make(CacheStrategyRegistry::class,),
                self::defaultVaryByHeaders($app),
            ),
        );
        $this->app->singleton(CacheGroupNormalizer::class);
        $this->app->singleton(
            CacheGroupResolver::class,
            static fn (Application $app): CacheGroupResolver => new CacheGroupResolver(
                $app->make(CacheGroupNormalizer::class),
                $app->make(CacheStrategyRegistry::class),
            ),
        );
        $this->app->singleton(
            CacheKeyGenerator::class,
            static fn (
                Application $app,
            ): CacheKeyGenerator => new CacheKeyGenerator(
                $app->make(OperationCacheEvaluator::class),
                $app->make(CacheGroupResolver::class),
                $app->make(CacheGroupGenerationManager::class),
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
                if (!$repository instanceof CacheRepository) {
                    // @codeCoverageIgnoreStart
                    throw new \LogicException('Laravel cache manager must return an Illuminate cache repository.',);
                    // @codeCoverageIgnoreEnd
                }

                return new LaravelCacheStore($repository,);
            },
        );

        $this->app->singleton(OperationCacheHandler::class, static fn (
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
                eventDispatcher: $app->make(EventDispatcherInterface::class),
            ),);
        $this->app->singleton(
            CacheGroupGenerationManager::class,
            static fn (Application $app): CacheGroupGenerationManager => new CacheGroupGenerationManager(
                $app->make(CacheStoreInterface::class),
            ),
        );
        $this->app->singleton(
            CacheInvalidator::class,
            static fn (Application $app): CacheInvalidator => new CacheInvalidator(
                $app->make(CacheGroupNormalizer::class),
                $app->make(CacheGroupGenerationManager::class),
                $app->make(EventDispatcherInterface::class),
            ),
        );
        $this->app->alias(CacheInvalidator::class, CacheInvalidatorInterface::class,);
        $this->app->singleton(
            OperationCacheInvalidator::class,
            static fn (Application $app): OperationCacheInvalidator => new OperationCacheInvalidator(
                $app->make(OperationCacheMetadataExtractor::class),
                $app->make(CacheGroupNormalizer::class),
                $app->make(CacheStrategyRegistry::class),
                $app->make(CacheInvalidatorInterface::class),
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

    /**
     * @return list<string>
     */
    private static function defaultVaryByHeaders(Application $app,): array
    {
        $headers = $app
            ->make(ConfigRepository::class)
            ->get('api-platform-operation-cache.vary_by_headers', []);
        if (!is_array($headers)) {
            throw new \InvalidArgumentException(
                'The api-platform-operation-cache.vary_by_headers configuration must be an array.',
            );
        }

        foreach ($headers as $header) {
            if (!is_string($header)) {
                throw new \InvalidArgumentException('Every default vary-by header must be a string.',);
            }
        }

        return array_values($headers);
    }
}
