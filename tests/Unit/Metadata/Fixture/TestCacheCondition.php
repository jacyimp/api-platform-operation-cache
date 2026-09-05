<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use Symfony\Component\HttpFoundation\Request;

final class TestCacheCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return true;
    }
}
