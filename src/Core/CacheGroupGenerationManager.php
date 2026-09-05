<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;

/**
 * @internal
 */
final readonly class CacheGroupGenerationManager
{
    private const BASELINE_GENERATION = '0';

    private const VERSION = 1;

    public function __construct(
        private CacheStoreInterface $cacheStore,
    ) {
    }

    /**
     * @param list<string> $groups
     *
     * @return array<string, string>
     */
    public function generationsFor(array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $targets = ['*' => true];

        foreach ($groups as $group) {
            $segments = explode(':', $group);
            $prefix = '';

            foreach ($segments as $index => $segment) {
                $prefix = $index === 0
                    ? $segment
                    : sprintf('%s:%s', $prefix, $segment);

                $targets[$index === count($segments) - 1
                    ? $prefix
                    : sprintf('%s:*', $prefix)] = true;
            }
        }

        $targets = array_keys($targets);
        sort($targets, SORT_STRING);

        $keyToTarget = [];

        foreach ($targets as $target) {
            $keyToTarget[$this->key($target)] = $target;
        }

        $stored = $this->cacheStore->getGenerations(array_keys($keyToTarget));
        $generations = [];

        foreach ($keyToTarget as $key => $target) {
            $generations[$target] = $stored[$key] ?? self::BASELINE_GENERATION;
        }

        return $generations;
    }

    public function invalidate(string $target): void
    {
        $this->cacheStore->putGeneration(
            $this->key($target),
            bin2hex(random_bytes(16)),
        );
    }

    private function key(string $target): string
    {
        return sprintf(
            'api_platform_operation_cache.g%d.%s',
            self::VERSION,
            hash('sha256', $target),
        );
    }
}
