<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\ApiPlatform;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;

/**
 * @internal
 */
final readonly class OperationCacheResourceMetadataCollectionFactory implements
    ResourceMetadataCollectionFactoryInterface
{
    /** @param list<string> $defaultVaryByHeaders */
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private OperationCacheMetadataExtractor $metadataExtractor,
        private array $defaultVaryByHeaders = [],
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = new ResourceMetadataCollection(
            $resourceClass,
            iterator_to_array($this->decorated->create($resourceClass)),
        );
        foreach ($collection as $index => $resource) {
            $operations = $resource->getOperations();
            if ($operations === null) {
                continue;
            }

            $operations = clone $operations;
            foreach ($operations as $name => $operation) {
                $cache = $this->metadataExtractor->extract($operation);
                $openapi = $operation->getOpenapi();
                if (
                    $cache === null
                    || !in_array($operation->getMethod(), ['GET', 'HEAD'], true)
                    || $openapi === false
                    || (is_object($openapi) && !$openapi instanceof Operation)
                ) {
                    continue;
                }

                $openapi = $openapi instanceof Operation ? $openapi : new Operation();
                $description = $openapi->getDescription()
                    ?? $operation->getDescription() ?? $openapi->getSummary() ?? '';
                $section = $this->describe($cache);
                if (!str_contains($description, $section)) {
                    $description = $description === '' ? $section : $description . "\n\n" . $section;
                }

                $operations->add($name, $operation->withOpenapi($openapi->withDescription($description)));
            }

            $collection[$index] = $resource->withOperations($operations);
        }

        return $collection;
    }

    private function describe(OperationCache $cache): string
    {
        $lines = [
            '### Caching',
            sprintf('Responses may be served from the server-side cache for up to %d seconds (TTL).', $cache->ttl),
            'Cache entries vary by operation, host, HTTP method, path, query parameters, and request format.',
        ];
        $headers = $cache->includeDefaultVary
            ? [...$this->defaultVaryByHeaders, ...$cache->varyByHeaders]
            : $cache->varyByHeaders;
        $headers = array_values(array_unique(array_map(
            static fn (string $header): string => strtolower(trim($header)),
            $headers,
        )));
        if ($headers !== []) {
            $lines[] = 'Cache entries also vary by request headers: `' . implode('`, `', $headers) . '`.';
        }

        if ($cache->varyByAuth !== false) {
            $lines[] = 'Cache entries vary by authenticated identity; anonymous requests share an anonymous identity.';
        }

        if ($cache->varyByResolver !== null) {
            $lines[] = 'Additional application-defined context separates cache entries.';
        }

        if ($cache->when !== null) {
            $lines[] = 'Caching applies only when the application-defined cache condition matches the request.';
        }

        $lines[] = 'Only successful responses are stored. Streams, binary files, and responses with '
            . '`no-store`, `Set-Cookie`, or `Vary: *` are not stored.';

        return implode("\n\n", $lines);
    }
}
