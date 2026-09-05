<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TestResponseMutator implements ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response {
        return $response;
    }

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response {
        return $response;
    }
}
