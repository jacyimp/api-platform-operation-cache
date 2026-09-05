<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

final readonly class OperationCache
{
    /**
     * @param list<VaryBy> $varyBy
     */
    public function __construct(
        public int $ttl,
        public array $varyBy = [],
    ) {
    }
}
