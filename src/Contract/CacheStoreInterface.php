<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;

interface CacheStoreInterface
{
    public function get(string $key): ?CachedResponse;

    public function put(
        string $key,
        CachedResponse $response,
        int $ttl,
    ): void;
}
