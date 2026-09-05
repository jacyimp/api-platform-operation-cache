<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

/**
 * @internal
 */
final readonly class CachedResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public string $content,
        public int $statusCode,
        public array $headers,
    ) {
    }
}
