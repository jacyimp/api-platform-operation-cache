<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheGroupResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class TestCacheGroupResolver implements CacheGroupResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(Request $request): array
    {
        return [];
    }
}
