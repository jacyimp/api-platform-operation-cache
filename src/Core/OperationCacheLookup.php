<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final readonly class OperationCacheLookup
{
    private function __construct(
        public ?Response $response,
        public ?OperationCacheContext $context,
    ) {
    }

    public static function bypass(): self
    {
        return new self(
            response: null,
            context: null,
        );
    }

    public static function hit(Response $response): self
    {
        return new self(
            response: $response,
            context: null,
        );
    }

    public static function miss(OperationCacheContext $context): self
    {
        return new self(
            response: null,
            context: $context,
        );
    }

    public function isHit(): bool
    {
        return $this->response !== null;
    }

    public function shouldStore(): bool
    {
        return $this->context !== null;
    }
}
