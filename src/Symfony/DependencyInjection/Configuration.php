<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(
            'api_platform_operation_cache',
        );

        $treeBuilder
            ->getRootNode()
            ->children()
            ->scalarNode('cache_pool')
            ->cannotBeEmpty()
            ->defaultValue('cache.app')
            ->end()
            ->end();

        return $treeBuilder;
    }
}
