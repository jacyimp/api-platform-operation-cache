<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class TestAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): string
    {
        return 'user-42';
    }
}
