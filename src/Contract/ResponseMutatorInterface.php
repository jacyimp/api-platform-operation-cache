<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response;

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response;
}
