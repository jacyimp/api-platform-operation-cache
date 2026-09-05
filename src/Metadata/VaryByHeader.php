<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

final readonly class VaryByHeader implements VaryBy
{
    public function __construct(
        public string $header,
    ) {
    }
}
