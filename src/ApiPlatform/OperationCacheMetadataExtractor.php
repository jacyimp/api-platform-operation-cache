<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheMetadataException;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;

/**
 * @internal
 */
final class OperationCacheMetadataExtractor
{
    public function extract(Operation $operation): ?OperationCache
    {
        $extraProperties = $operation->getExtraProperties() ?? [];
        $metadataByObjectId = [];

        foreach ($extraProperties as $key => $metadata) {
            if ($metadata instanceof OperationCache) {
                $metadataByObjectId[spl_object_id($metadata)] = $metadata;
                continue;
            }

            if ($key === OperationCache::class) {
                throw new InvalidOperationCacheMetadataException(sprintf(
                    'Extra property "%s" must be an instance of %s.',
                    OperationCache::class,
                    OperationCache::class,
                ));
            }
        }

        if (count($metadataByObjectId) > 1) {
            throw new InvalidOperationCacheMetadataException(sprintf(
                'Operation extra properties must contain at most one instance of %s.',
                OperationCache::class,
            ));
        }

        return array_values($metadataByObjectId)[0] ?? null;
    }

    /**
     * @return list<OperationCacheInvalidation>
     */
    public function extractInvalidations(Operation $operation): array
    {
        $extraProperties = $operation->getExtraProperties() ?? [];
        $invalidations = [];
        foreach ($extraProperties as $key => $metadata) {
            if ($metadata instanceof OperationCacheInvalidation) {
                $invalidations[] = $metadata;
                continue;
            }

            if ($key === OperationCacheInvalidation::class) {
                throw new InvalidOperationCacheMetadataException(sprintf(
                    'Extra property "%s" must be an instance of %s.',
                    OperationCacheInvalidation::class,
                    OperationCacheInvalidation::class,
                ));
            }
        }

        return $invalidations;
    }
}
