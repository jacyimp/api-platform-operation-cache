<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResponseBehaviorMutator implements ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response {
        $response->headers->set(
            'X-Cached-Copy',
            'yes',
        );

        $response->headers->set(
            'X-Excluded',
            'should-not-survive',
        );

        $response->headers->set(
            'Age',
            '60',
        );

        $response->headers->set(
            'Set-Cookie',
            'session=from-mutator',
        );

        return $response;
    }

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response {
        $response->headers->set(
            'X-Cache-Hit',
            'yes',
        );

        return $response;
    }
}
