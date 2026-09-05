<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Event;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CacheHitEvent
{
    public function __construct(
        public HttpOperation $operation,
        public Request $request,
        public Response $response,
    ) {
    }
}
