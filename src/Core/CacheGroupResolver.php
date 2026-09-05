<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final readonly class CacheGroupResolver
{
    public function __construct(
        private CacheGroupNormalizer $groupNormalizer,
        private CacheStrategyRegistry $strategyRegistry,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolve(
        OperationCache $cache,
        Request $request,
    ): array {
        $groups = array_map(
            fn (string $group): string => $this->groupNormalizer->interpolate(
                $group,
                $request,
            ),
            $cache->groups,
        );

        if ($cache->groupResolver !== null) {
            $groups = [
                ...$groups,
                ...$this->strategyRegistry
                    ->cacheGroupResolver($cache->groupResolver)
                    ->resolve($request),
            ];
        }

        return $this->groupNormalizer->memberships($groups);
    }
}
