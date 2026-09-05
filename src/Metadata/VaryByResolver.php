<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;

final readonly class VaryByResolver implements VaryBy
{
    /**
     * @param class-string<VaryResolverInterface> $resolver
     */
    public function __construct(
        public string $resolver,
    ) {
    }
}
