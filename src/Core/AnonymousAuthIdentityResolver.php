<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class AnonymousAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}
