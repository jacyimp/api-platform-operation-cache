<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Http\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TestResponseMutator implements ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response {
        $response->setContent('cached');
        $response->headers->remove('X-Original');
        $response->headers->set('X-Cached', 'yes');

        return $response;
    }

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response {
        $response->setContent('served');
        $response->headers->set(
            'X-Cache-Hit',
            'yes',
        );

        return $response;
    }
}
