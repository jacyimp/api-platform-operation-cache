<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use Symfony\Component\HttpFoundation\Request;

final class KeyNeverCacheCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return false;
    }
}
