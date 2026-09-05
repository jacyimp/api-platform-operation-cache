<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidationConditionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class NeverInvalidationCondition implements CacheInvalidationConditionInterface
{
    public function matches(Request $request, Response $response): bool
    {
        return false;
    }
}
