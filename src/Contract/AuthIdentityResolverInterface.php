<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

interface AuthIdentityResolverInterface
{
    public function resolve(): ?string;
}
