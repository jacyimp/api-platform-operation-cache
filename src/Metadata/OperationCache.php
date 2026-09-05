<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

final readonly class OperationCache
{
    /**
     * @param list<string>|null $vary
     */
    public function __construct(
        public ?bool $enabled = null,
        public ?int $ttl = null,
        public ?array $vary = null,
    ) {
    }
}
