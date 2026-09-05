<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class HandlerAnonymousAuthResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}
