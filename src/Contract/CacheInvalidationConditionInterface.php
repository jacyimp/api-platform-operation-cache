<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface CacheInvalidationConditionInterface
{
    public function matches(
        Request $request,
        Response $response,
    ): bool;
}
