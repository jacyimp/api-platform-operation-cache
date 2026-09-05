<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Event;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\HttpFoundation\Request;

final readonly class CacheMissEvent
{
    public function __construct(
        public HttpOperation $operation,
        public Request $request,
    ) {
    }
}
