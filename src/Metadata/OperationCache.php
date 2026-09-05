<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;

final readonly class OperationCache
{
    /**
     * @param list<string> $varyByHeaders
     * @param false|true|class-string<AuthIdentityResolverInterface> $varyByAuth
     * @param class-string<VaryResolverInterface>|null $varyByResolver
     * @param class-string<CacheConditionInterface>|null $when
     * @param list<string> $excludeResponseHeaders
     * @param class-string<ResponseMutatorInterface>|null $responseMutator
     */
    public function __construct(
        public int $ttl,
        public array $varyByHeaders = [],
        public bool|string $varyByAuth = false,
        public ?string $varyByResolver = null,
        public ?string $when = null,
        public array $excludeResponseHeaders = [],
        public bool $excludeDefaultResponseHeaders = true,
        public ?string $responseMutator = null,
    ) {
    }
}
