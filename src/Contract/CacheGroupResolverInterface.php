<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

use Symfony\Component\HttpFoundation\Request;

interface CacheGroupResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(Request $request): array;
}
