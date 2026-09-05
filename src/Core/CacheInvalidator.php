<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidatorInterface;

final readonly class CacheInvalidator implements CacheInvalidatorInterface
{
    public function __construct(
        private CacheGroupNormalizer $groupNormalizer,
        private CacheGroupGenerationManager $generationManager,
    ) {
    }

    /**
     * @param list<string> $groups
     */
    public function invalidateGroups(array $groups): void
    {
        foreach ($this->groupNormalizer->invalidationTargets($groups) as $group) {
            $this->generationManager->invalidate($group);
        }
    }
}
