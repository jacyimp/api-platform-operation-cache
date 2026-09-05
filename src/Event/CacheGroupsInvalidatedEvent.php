<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Event;

final readonly class CacheGroupsInvalidatedEvent
{
    /** @param list<string> $groups */
    public function __construct(public array $groups)
    {
    }
}
