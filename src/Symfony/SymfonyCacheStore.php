<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony;

use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @internal
 */
final readonly class SymfonyCacheStore implements CacheStoreInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function get(string $key): ?CachedResponse
    {
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        if (!$value instanceof CachedResponse) {
            $this->cache->deleteItem($key);

            return null;
        }

        return $value;
    }

    public function put(
        string $key,
        CachedResponse $response,
        int $ttl,
    ): void {
        $item = $this->cache->getItem($key);

        $item->set($response);
        $item->expiresAfter($ttl);

        $this->cache->save($item);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    public function getGenerations(array $keys): array
    {
        $generations = [];
        foreach ($this->cache->getItems($keys) as $item) {
            if (!$item instanceof CacheItemInterface) {
                continue;
            }

            if (!$item->isHit()) {
                continue;
            }

            $value = $item->get();
            if (!is_string($value)) {
                continue;
            }

            $generations[$item->getKey()] = $value;
        }

        return $generations;
    }

    public function putGeneration(string $key, string $generation,): void
    {
        $item = $this->cache->getItem($key);
        $item->set($generation);
        $this->cache->save($item);
    }
}
