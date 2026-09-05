<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface CacheInvalidationGroupResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(
        Request $request,
        Response $response,
        HttpOperation $operation,
    ): array;
}
