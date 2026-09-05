<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;

final class HandlerTestCacheStore implements CacheStoreInterface
{
    public int $getCalls = 0;

    public int $putCalls = 0;

    public ?CachedResponse $lastCached = null;

    public ?int $lastTtl = null;

    public function __construct(
        private readonly ?CachedResponse $cached = null,
    ) {
    }

    public function get(string $key): ?CachedResponse
    {
        ++$this->getCalls;

        return $this->cached;
    }

    public function put(
        string $key,
        CachedResponse $response,
        int $ttl,
    ): void {
        ++$this->putCalls;

        $this->lastCached = $response;
        $this->lastTtl = $ttl;
    }
}
