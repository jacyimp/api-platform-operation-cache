<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Http\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CookieAddingResponseMutator implements ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response {
        $response->headers->set(
            'Set-Cookie',
            'session=abc',
        );

        return $response;
    }

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response {
        return $response;
    }
}
