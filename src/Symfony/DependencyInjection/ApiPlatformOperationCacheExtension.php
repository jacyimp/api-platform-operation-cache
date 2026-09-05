<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony\DependencyInjection;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheGroupResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidationConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidationGroupResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidatorInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Core\AnonymousAuthIdentityResolver;
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
use JacyImp\ApiPlatformOperationCache\Symfony\EventListener\ApiPlatformOperationCacheListener;
use JacyImp\ApiPlatformOperationCache\Symfony\SymfonyAuthIdentityResolver;
use JacyImp\ApiPlatformOperationCache\Symfony\SymfonyCacheStore;
use JacyImp\ApiPlatformOperationCache\Symfony\SymfonyCacheStrategyLocator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal
 */
final class ApiPlatformOperationCacheExtension extends Extension
{
    public const VARY_RESOLVER_TAG =
        'jacyimp.api_platform_operation_cache.vary_resolver';

    public const AUTH_IDENTITY_RESOLVER_TAG =
        'jacyimp.api_platform_operation_cache.auth_identity_resolver';

    public const CONDITION_TAG =
        'jacyimp.api_platform_operation_cache.condition';

    public const RESPONSE_MUTATOR_TAG =
        'jacyimp.api_platform_operation_cache.response_mutator';

    public const CACHE_GROUP_RESOLVER_TAG =
        'jacyimp.api_platform_operation_cache.cache_group_resolver';

    public const INVALIDATION_CONDITION_TAG =
        'jacyimp.api_platform_operation_cache.invalidation_condition';

    public const INVALIDATION_GROUP_RESOLVER_TAG =
        'jacyimp.api_platform_operation_cache.invalidation_group_resolver';

    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(
        array $configs,
        ContainerBuilder $container,
    ): void {
        /** @var array{cache_pool: string, vary_by_headers: list<string>} $config */
        $config = $this->processConfiguration(
            new Configuration(),
            $configs,
        );

        $this->registerStrategyAutoconfiguration($container);
        $this->registerStrategyRegistry($container);
        $this->registerAuthResolver($container);
        $this->registerCore(
            $container,
            $config['cache_pool'],
            $config['vary_by_headers'],
        );
        $this->registerListener($container);
    }

    private function registerStrategyAutoconfiguration(
        ContainerBuilder $container,
    ): void {
        $container
            ->registerForAutoconfiguration(VaryResolverInterface::class)
            ->addTag(self::VARY_RESOLVER_TAG);

        $container
            ->registerForAutoconfiguration(AuthIdentityResolverInterface::class)
            ->addTag(self::AUTH_IDENTITY_RESOLVER_TAG);

        $container
            ->registerForAutoconfiguration(CacheConditionInterface::class)
            ->addTag(self::CONDITION_TAG);

        $container
            ->registerForAutoconfiguration(ResponseMutatorInterface::class)
            ->addTag(self::RESPONSE_MUTATOR_TAG);

        $container
            ->registerForAutoconfiguration(CacheGroupResolverInterface::class)
            ->addTag(self::CACHE_GROUP_RESOLVER_TAG);

        $container
            ->registerForAutoconfiguration(CacheInvalidationConditionInterface::class)
            ->addTag(self::INVALIDATION_CONDITION_TAG);

        $container
            ->registerForAutoconfiguration(CacheInvalidationGroupResolverInterface::class)
            ->addTag(self::INVALIDATION_GROUP_RESOLVER_TAG);
    }

    private function registerStrategyRegistry(
        ContainerBuilder $container,
    ): void {
        $container
            ->register(
                SymfonyCacheStrategyLocator::class,
                SymfonyCacheStrategyLocator::class,
            )
            ->setArguments([
                new TaggedIteratorArgument(
                    self::VARY_RESOLVER_TAG,
                ),
                new TaggedIteratorArgument(
                    self::AUTH_IDENTITY_RESOLVER_TAG,
                ),
                new TaggedIteratorArgument(
                    self::CONDITION_TAG,
                ),
                new TaggedIteratorArgument(
                    self::RESPONSE_MUTATOR_TAG,
                ),
                new TaggedIteratorArgument(
                    self::CACHE_GROUP_RESOLVER_TAG,
                ),
                new TaggedIteratorArgument(
                    self::INVALIDATION_CONDITION_TAG,
                ),
                new TaggedIteratorArgument(
                    self::INVALIDATION_GROUP_RESOLVER_TAG,
                ),
            ]);

        $container
            ->register(
                CacheStrategyRegistry::class,
                CacheStrategyRegistry::class,
            )
            ->setArguments([
                new Reference(
                    SymfonyCacheStrategyLocator::class,
                ),
            ]);
    }

    private function registerAuthResolver(
        ContainerBuilder $container,
    ): void {
        if (interface_exists(TokenStorageInterface::class)) {
            $container
                ->register(
                    SymfonyAuthIdentityResolver::class,
                    SymfonyAuthIdentityResolver::class,
                )
                ->setArguments([
                    new Reference(
                        'security.untracked_token_storage',
                        ContainerInterface::NULL_ON_INVALID_REFERENCE,
                    ),
                ])
                ->addTag(
                    self::AUTH_IDENTITY_RESOLVER_TAG,
                );

            $container->setAlias(
                AuthIdentityResolverInterface::class,
                SymfonyAuthIdentityResolver::class,
            );

            return;
        }

        // @codeCoverageIgnoreStart
        // This path is exercised when the optional Symfony Security package is absent.
        $container->register(
            AnonymousAuthIdentityResolver::class,
            AnonymousAuthIdentityResolver::class,
        );

        $container->setAlias(
            AuthIdentityResolverInterface::class,
            AnonymousAuthIdentityResolver::class,
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param list<string> $defaultVaryByHeaders
     */
    private function registerCore(
        ContainerBuilder $container,
        string $cachePool,
        array $defaultVaryByHeaders,
    ): void {
        $container->register(
            OperationCacheMetadataExtractor::class,
        );

        $container->register(
            ResponseCachePolicy::class,
        );

        $container
            ->register(OperationCacheEvaluator::class)
            ->setArguments([
                new Reference(
                    AuthIdentityResolverInterface::class,
                ),
                new Reference(
                    CacheStrategyRegistry::class,
                ),
                $defaultVaryByHeaders,
            ]);

        $container->register(CacheGroupNormalizer::class);

        $container
            ->register(CacheGroupResolver::class)
            ->setArguments([
                new Reference(CacheGroupNormalizer::class),
                new Reference(CacheStrategyRegistry::class),
            ]);

        $container
            ->register(CacheKeyGenerator::class)
            ->setArguments([
                new Reference(
                    OperationCacheEvaluator::class,
                ),
                new Reference(CacheGroupResolver::class),
                new Reference(CacheGroupGenerationManager::class),
            ]);

        $container
            ->register(CachedResponseFactory::class)
            ->setArguments([
                new Reference(
                    CacheStrategyRegistry::class,
                ),
            ]);

        $container
            ->register(SymfonyCacheStore::class)
            ->setArguments([
                new Reference($cachePool),
            ]);

        $container->setAlias(
            CacheStoreInterface::class,
            SymfonyCacheStore::class,
        );

        $container
            ->register(CacheGroupGenerationManager::class)
            ->setArguments([
                new Reference(CacheStoreInterface::class),
            ]);

        $container
            ->register(CacheInvalidator::class)
            ->setArguments([
                new Reference(CacheGroupNormalizer::class),
                new Reference(CacheGroupGenerationManager::class),
                new Reference(EventDispatcherInterface::class),
            ]);

        $container->setAlias(
            CacheInvalidatorInterface::class,
            CacheInvalidator::class,
        )->setPublic(true);

        $container
            ->register(OperationCacheInvalidator::class)
            ->setArguments([
                new Reference(OperationCacheMetadataExtractor::class),
                new Reference(CacheGroupNormalizer::class),
                new Reference(CacheStrategyRegistry::class),
                new Reference(CacheInvalidatorInterface::class),
            ]);

        $container
            ->register(OperationCacheHandler::class)
            ->setArguments([
                new Reference(
                    OperationCacheMetadataExtractor::class,
                ),
                new Reference(
                    ResponseCachePolicy::class,
                ),
                new Reference(
                    CacheKeyGenerator::class,
                ),
                new Reference(
                    CacheStoreInterface::class,
                ),
                new Reference(
                    CachedResponseFactory::class,
                ),
                new Reference(EventDispatcherInterface::class),
            ]);
    }

    private function registerListener(
        ContainerBuilder $container,
    ): void {
        $container
            ->register(
                ApiPlatformOperationCacheListener::class,
            )
            ->setArguments([
                new Reference(
                    OperationCacheHandler::class,
                ),
                new Reference(
                    ResourceMetadataCollectionFactoryInterface::class,
                    ContainerInterface::NULL_ON_INVALID_REFERENCE,
                ),
                new Reference(
                    OperationCacheInvalidator::class,
                ),
            ])
            ->addTag(
                'kernel.event_listener',
                [
                    'event' => 'kernel.request',
                    'method' => 'onKernelRequest',
                    'priority' => 6,
                ],
            )
            ->addTag(
                'kernel.event_listener',
                [
                    'event' => 'kernel.response',
                    'method' => 'onKernelResponse',
                    'priority' => -1,
                ],
            );
    }
}
