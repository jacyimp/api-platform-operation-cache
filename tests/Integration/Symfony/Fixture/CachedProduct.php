<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;
use JacyImp\ApiPlatformOperationCache\Tests\Fixture\ResponseBehaviorMutator;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/cached-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(ttl: 300, groups: ['product:{id}'],),
            ],
        ),
        new Patch(
            uriTemplate: '/cached-products/{id}',
            status: 204,
            read: false,
            deserialize: false,
            output: false,
            processor: ProductWriteProcessor::class,
            extraProperties: [
                new OperationCacheInvalidation(group: 'product:{id}'),
            ],
        ),
        new Patch(
            uriTemplate: '/failed-cached-products/{id}',
            status: 422,
            read: false,
            deserialize: false,
            output: false,
            processor: ProductWriteProcessor::class,
            extraProperties: [
                new OperationCacheInvalidation(group: 'product:{id}'),
            ],
        ),
        new Get(
            uriTemplate: '/conditionally-uncached-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    when: NeverCacheCondition::class,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/header-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    varyByHeaders: [
                        'Accept-Language',
                    ],
                ),
            ],
        ),
        new Get(
            uriTemplate: '/default-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(ttl: 300),
            ],
        ),
        new Get(
            uriTemplate: '/no-default-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    includeDefaultVary: false,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/auth-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    varyByAuth: RequestHeaderAuthIdentityResolver::class,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/resolver-vary-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    varyByResolver: TenantVaryResolver::class,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/response-mutator-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    responseMutator: ResponseBehaviorMutator::class,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/response-exclusion-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    excludeResponseHeaders: [
                        'X-Excluded',
                    ],
                    responseMutator: ResponseBehaviorMutator::class,
                ),
            ],
        ),
        new Get(
            uriTemplate: '/response-default-products/{id}',
            provider: CountingProductProvider::class,
            extraProperties: [
                new OperationCache(
                    ttl: 300,
                    excludeDefaultResponseHeaders: false,
                    responseMutator: ResponseBehaviorMutator::class,
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
