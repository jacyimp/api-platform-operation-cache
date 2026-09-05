<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Laravel;

use Illuminate\Cache\Repository;
use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;

/**
 * @internal
 */
final readonly class LaravelCacheStore implements CacheStoreInterface
{
    public function __construct(
        private Repository $cache,
    ) {
    }

    public function get(string $key): ?CachedResponse
    {
        $value = $this->cache->get($key);

        return $value instanceof CachedResponse
            ? $value
            : null;
    }

    public function put(
        string $key,
        CachedResponse $response,
        int $ttl,
    ): void {
        $this->cache->put(
            $key,
            $response,
            $ttl,
        );
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    public function getGenerations(array $keys): array
    {
        $values = $this->cache->many($keys);
        $generations = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $generations[$key] = $value;
        }

        return $generations;
    }

    public function putGeneration(string $key, string $generation,): void
    {
        $this->cache->forever($key, $generation);
    }
}
