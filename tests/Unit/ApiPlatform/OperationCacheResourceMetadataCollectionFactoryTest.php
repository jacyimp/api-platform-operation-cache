<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheResourceMetadataCollectionFactory;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestCacheCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestVaryResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationCacheResourceMetadataCollectionFactory::class)]
final class OperationCacheResourceMetadataCollectionFactoryTest extends TestCase
{
    public function testItDocumentsEffectiveCachingWithoutMutatingOriginalMetadata(): void
    {
        $openapi = new Operation(description: 'Existing description.', summary: 'Product');
        $get = new Get(openapi: $openapi, extraProperties: [
            new OperationCache(
                ttl: 300,
                varyByHeaders: ['X-Currency', 'Accept-Language'],
                varyByAuth: true,
                varyByResolver: TestVaryResolver::class,
                when: TestCacheCondition::class,
            ),
        ]);
        $collection = new ResourceMetadataCollection(self::class, [new ApiResource(operations: [
            'cached' => $get,
        ])]);
        $inner = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->with(self::class)->willReturn($collection);
        $factory = new OperationCacheResourceMetadataCollectionFactory(
            $inner,
            new OperationCacheMetadataExtractor(),
            [' X-Currency ', 'X-Tenant'],
        );

        $collection->getOperation('cached');
        $operation = $factory->create(self::class)->getOperation('cached');
        self::assertInstanceOf(HttpOperation::class, $operation);
        $result = $operation->getOpenapi();
        self::assertInstanceOf(Operation::class, $result);
        self::assertSame('Product', $result->getSummary());
        $description = $result->getDescription();
        self::assertNotNull($description);
        self::assertStringStartsWith("Existing description.\n\n### Caching", $description);
        self::assertStringContainsString('300 seconds (TTL)', $description);
        self::assertStringContainsString('`x-currency`, `x-tenant`, `accept-language`', $description);
        self::assertStringContainsString('authenticated identity', $description);
        self::assertStringContainsString('application-defined context', $description);
        self::assertStringContainsString('condition matches', $description);
        self::assertStringContainsString('`no-store`, `Set-Cookie`, or `Vary: *`', $description);
        $original = $get->getOpenapi();
        self::assertInstanceOf(Operation::class, $original);
        self::assertSame('Existing description.', $original->getDescription());
        $operation = $factory->create(self::class)->getOperation('cached');
        self::assertInstanceOf(HttpOperation::class, $operation);
        $repeated = $operation->getOpenapi();
        self::assertInstanceOf(Operation::class, $repeated);
        self::assertSame($description, $repeated->getDescription());
    }

    public function testItSkipsUncachedHiddenAndWriteOperationsAndHonorsDefaultVaryOptOut(): void
    {
        $cache = new OperationCache(ttl: 60, includeDefaultVary: false);
        $plain = new Get();
        $hidden = new Get(openapi: false, extraProperties: [$cache]);
        $write = new Post(extraProperties: [$cache]);
        $inner = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $inner->method('create')->willReturn(new ResourceMetadataCollection(self::class, [
            new ApiResource(operations: [
                'plain' => $plain,
                'hidden' => $hidden,
                'write' => $write,
                'cached' => new Get(description: 'Read a product.', extraProperties: [$cache]),
            ]),
            new ApiResource(),
        ]));
        $factory = new OperationCacheResourceMetadataCollectionFactory(
            $inner,
            new OperationCacheMetadataExtractor(),
            ['X-Tenant'],
        );
        $result = $factory->create(self::class);
        self::assertSame($plain, $result->getOperation('plain'));
        self::assertSame($hidden, $result->getOperation('hidden'));
        self::assertSame($write, $result->getOperation('write'));
        $operation = $result->getOperation('cached');
        self::assertInstanceOf(HttpOperation::class, $operation);
        $openapi = $operation->getOpenapi();
        self::assertInstanceOf(Operation::class, $openapi);
        $description = $openapi->getDescription();
        self::assertNotNull($description);
        self::assertStringStartsWith("Read a product.\n\n### Caching", $description);
        self::assertStringNotContainsString('x-tenant', $description);
        self::assertStringNotContainsString('authenticated identity', $description);
    }
}
