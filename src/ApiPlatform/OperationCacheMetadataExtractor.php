<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheMetadataException;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;

/**
 * @internal
 */
final class OperationCacheMetadataExtractor
{
    public function extract(Operation $operation): ?OperationCache
    {
        $extraProperties = $operation->getExtraProperties();

        if (!array_key_exists(OperationCache::class, $extraProperties)) {
            return null;
        }

        $metadata = $extraProperties[OperationCache::class];

        if (!$metadata instanceof OperationCache) {
            throw new InvalidOperationCacheMetadataException(sprintf(
                'Extra property "%s" must be an instance of %s.',
                OperationCache::class,
                OperationCache::class,
            ));
        }

        return $metadata;
    }
}
