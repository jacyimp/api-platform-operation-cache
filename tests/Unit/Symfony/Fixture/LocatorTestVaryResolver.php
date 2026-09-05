<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class LocatorTestVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return 'test';
    }
}
