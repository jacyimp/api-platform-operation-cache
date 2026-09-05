<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/cached-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/conditionally-uncached-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                    when: NeverCacheCondition::class,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/header-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                    varyByHeaders: [
                        'Accept-Language',
                    ],
                ),
            ],
        ),
        new Get(
            uriTemplate: '/auth-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                    varyByAuth: RequestHeaderAuthIdentityResolver::class,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/resolver-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                OperationCache::class => new OperationCache(
                    ttl: 300,
                    varyByResolver: TenantVaryResolver::class,
                ),
            ],
        ),
    ],
    formats: ['json'],
)]
final readonly class CachedProduct
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $value,
    ) {
    }
}
