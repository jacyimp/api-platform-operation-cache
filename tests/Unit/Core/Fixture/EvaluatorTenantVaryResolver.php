<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class EvaluatorTenantVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        $tenant = $request->attributes->get('tenant');

        if (!is_string($tenant)) {
            throw new \LogicException('Tenant must be a string.');
        }

        return $tenant;
    }
}
