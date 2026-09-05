<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use Symfony\Component\HttpFoundation\Request;

final class LocatorTestCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return true;
    }
}
