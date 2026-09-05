<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidatorInterface;
use JacyImp\ApiPlatformOperationCache\Event\CacheGroupsInvalidatedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CacheInvalidator implements CacheInvalidatorInterface
{
    public function __construct(
        private CacheGroupNormalizer $groupNormalizer,
        private CacheGroupGenerationManager $generationManager,
        private EventDispatcherInterface $eventDispatcher = new NullEventDispatcher(),
    ) {
    }

    /**
     * @param list<string> $groups
     */
    public function invalidateGroups(array $groups): void
    {
        $targets = $this->groupNormalizer->invalidationTargets($groups);

        foreach ($targets as $group) {
            $this->generationManager->invalidate($group);
        }

        if ($targets === []) {
            return;
        }

        $this->eventDispatcher->dispatch(new CacheGroupsInvalidatedEvent($targets));
    }
}
