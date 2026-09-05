<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use ApiPlatform\Metadata\HttpOperation;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidatorInterface;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final readonly class OperationCacheInvalidator
{
    public function __construct(
        private OperationCacheMetadataExtractor $metadataExtractor,
        private CacheGroupNormalizer $groupNormalizer,
        private CacheStrategyRegistry $strategyRegistry,
        private CacheInvalidatorInterface $cacheInvalidator,
    ) {
    }

    public function invalidate(
        HttpOperation $operation,
        Request $request,
        Response $response,
    ): void {
        if (!$response->isSuccessful()) {
            return;
        }

        $targets = [];

        foreach ($this->metadataExtractor->extractInvalidations($operation) as $invalidation) {
            if (!$this->conditionMatches($invalidation, $request, $response)) {
                continue;
            }

            if ($invalidation->group !== null) {
                $targets[] = $this->groupNormalizer->interpolate(
                    $invalidation->group,
                    $request,
                );
            }

            if ($invalidation->groupResolver === null) {
                continue;
            }

            $targets = [
                ...$targets,
                ...$this->strategyRegistry
                    ->invalidationGroupResolver($invalidation->groupResolver)
                    ->resolve($request, $response, $operation),
            ];
        }

        if ($targets === []) {
            return;
        }

        $this->cacheInvalidator->invalidateGroups($targets);
    }

    private function conditionMatches(
        OperationCacheInvalidation $invalidation,
        Request $request,
        Response $response,
    ): bool {
        if ($invalidation->when === null) {
            return true;
        }

        return $this->strategyRegistry
            ->invalidationCondition($invalidation->when)
            ->matches($request, $response);
    }
}
