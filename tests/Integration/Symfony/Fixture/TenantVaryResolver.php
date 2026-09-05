<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class TenantVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        $tenant = trim(
            (string) $request->headers->get('X-Tenant', ''),
        );

        return $tenant === ''
            ? 'default'
            : $tenant;
    }
}
