<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

interface CacheInvalidatorInterface
{
    /**
     * @param list<string> $groups
     */
    public function invalidateGroups(array $groups): void;
}
