<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use Symfony\Component\HttpFoundation\Request;

final class NeverCacheCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return false;
    }
}
