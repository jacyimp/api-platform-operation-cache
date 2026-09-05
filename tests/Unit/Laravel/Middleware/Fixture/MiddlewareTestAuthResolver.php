<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel\Middleware\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class MiddlewareTestAuthResolver implements AuthIdentityResolverInterface
{
    public function resolve(
        SymfonyRequest $request,
    ): ?string {
        return null;
    }
}
