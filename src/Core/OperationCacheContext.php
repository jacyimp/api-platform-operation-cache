<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;

/**
 * @internal
 */
final readonly class OperationCacheContext
{
    public function __construct(
        public OperationCache $cache,
        public string $key,
    ) {
    }
}
