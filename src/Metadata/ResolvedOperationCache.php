<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

final readonly class ResolvedOperationCache
{
    /**
     * @param list<string> $vary
     */
    public function __construct(
        public bool $enabled,
        public int $ttl,
        public array $vary,
    ) {
    }
}
