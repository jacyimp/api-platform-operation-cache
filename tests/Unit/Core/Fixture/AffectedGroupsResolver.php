<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use ApiPlatform\Metadata\HttpOperation;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidationGroupResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AffectedGroupsResolver implements CacheInvalidationGroupResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(
        Request $request,
        Response $response,
        HttpOperation $operation,
    ): array {
        return ['vendor:12:products', 'products'];
    }
}
