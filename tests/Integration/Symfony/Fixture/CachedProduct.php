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
