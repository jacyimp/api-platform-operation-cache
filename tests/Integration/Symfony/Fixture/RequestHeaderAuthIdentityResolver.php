<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class RequestHeaderAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $identity = trim(
            (string) $request->headers->get('X-User', ''),
        );

        return $identity === ''
            ? null
            : $identity;
    }
}
