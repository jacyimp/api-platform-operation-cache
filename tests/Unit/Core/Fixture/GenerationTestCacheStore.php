<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;

final class GenerationTestCacheStore implements CacheStoreInterface
{
    /** @var array<string, string> */
    public array $generations = [];

    /** @var list<string> */
    public array $lastKeys = [];

    public function get(string $key): ?CachedResponse
    {
        return null;
    }

    public function put(string $key, CachedResponse $response, int $ttl): void
    {
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    public function getGenerations(array $keys): array
    {
        $this->lastKeys = $keys;

        return array_intersect_key($this->generations, array_flip($keys));
    }

    public function putGeneration(string $key, string $generation): void
    {
        $this->generations[$key] = $generation;
    }
}
