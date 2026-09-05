<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheException;

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
        if ($ttl < 1) {
            throw new InvalidOperationCacheException(
                'Operation cache TTL must be greater than zero.',
            );
        }

        $this->assertHeaders(
            $varyByHeaders,
            'Vary-by header',
        );

        $this->assertHeaders(
            $excludeResponseHeaders,
            'Excluded response header',
        );

        if (
            is_string($varyByAuth)
            && trim($varyByAuth) === ''
        ) {
            throw new InvalidOperationCacheException(
                'Authentication vary resolver cannot be empty.',
            );
        }

        $this->assertOptionalService(
            $varyByResolver,
            'Vary resolver',
        );

        $this->assertOptionalService(
            $when,
            'Cache condition',
        );

        $this->assertOptionalService(
            $responseMutator,
            'Response mutator',
        );
    }

    /**
     * @param list<string> $headers
     */
    private function assertHeaders(
        array $headers,
        string $label,
    ): void {
        $seen = [];

        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));

            if ($normalized === '') {
                throw new InvalidOperationCacheException(sprintf(
                    '%s cannot be empty.',
                    $label,
                ));
            }

            if (isset($seen[$normalized])) {
                throw new InvalidOperationCacheException(sprintf(
                    '%s "%s" is declared more than once.',
                    $label,
                    $header,
                ));
            }

            $seen[$normalized] = true;
        }
    }

    private function assertOptionalService(
        ?string $service,
        string $label,
    ): void {
        if (
            $service !== null
            && trim($service) === ''
        ) {
            throw new InvalidOperationCacheException(sprintf(
                '%s cannot be empty.',
                $label,
            ));
        }
    }
}
