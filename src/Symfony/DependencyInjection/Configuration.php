<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
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

        $rootNode = $this->asArrayNode($treeBuilder->getRootNode());

        $children = $rootNode->children();

        $children
            ->scalarNode('cache_pool')
            ->cannotBeEmpty()
            ->defaultValue('cache.app');

        $varyByHeaders = $children->arrayNode('vary_by_headers');

        $varyByHeaders
            ->scalarPrototype()
            ->cannotBeEmpty();

        $varyByHeaders->defaultValue([]);

        return $treeBuilder;
    }

    private function asArrayNode(NodeDefinition $node): ArrayNodeDefinition
    {
        if (!$node instanceof ArrayNodeDefinition) {
            throw new \LogicException('The configuration root node must be an array node.');
        }

        return $node;
    }
}
