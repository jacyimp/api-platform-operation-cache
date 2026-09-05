<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use Symfony\Component\HttpFoundation\Request;

final class RegistryTestCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return true;
    }
}
