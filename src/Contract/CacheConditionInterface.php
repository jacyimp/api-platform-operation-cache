<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

use Symfony\Component\HttpFoundation\Request;

interface CacheConditionInterface
{
    public function matches(Request $request): bool;
}
