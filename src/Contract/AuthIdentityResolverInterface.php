<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

use Symfony\Component\HttpFoundation\Request;

interface AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string;
}
