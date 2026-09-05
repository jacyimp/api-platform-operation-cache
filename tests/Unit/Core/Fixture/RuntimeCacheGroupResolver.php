<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheGroupResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class RuntimeCacheGroupResolver implements CacheGroupResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(Request $request): array
    {
        return ['vendor:12:products', 'products'];
    }
}
