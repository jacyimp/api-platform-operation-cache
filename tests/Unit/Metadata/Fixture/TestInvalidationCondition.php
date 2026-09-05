<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidationConditionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TestInvalidationCondition implements CacheInvalidationConditionInterface
{
    public function matches(Request $request, Response $response): bool
    {
        return true;
    }
}
