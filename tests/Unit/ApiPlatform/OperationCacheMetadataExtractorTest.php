<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheMetadataException;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationCacheMetadataExtractor::class)]
final class OperationCacheMetadataExtractorTest extends TestCase
{
    private OperationCacheMetadataExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new OperationCacheMetadataExtractor();
    }

    public function testItReturnsNullWhenOperationHasNoCacheMetadata(): void
    {
        self::assertNull($this->extractor->extract(new Get()));
    }

    public function testItExtractsInstanceBasedOperationCacheMetadata(): void
    {
        $metadata = new OperationCache(
            ttl: 300,
            varyByHeaders: ['Accept-Language'],
        );

        $operation = new Get(
            extraProperties: [
                $metadata,
            ],
        );

        self::assertSame($metadata, $this->extractor->extract($operation));
    }

    public function testItExtractsClassKeyedOperationCacheMetadataForBackwardCompatibility(): void
    {
        $metadata = new OperationCache(ttl: 300);

        self::assertSame($metadata, $this->extractor->extract(new Get(
            extraProperties: [OperationCache::class => $metadata],
        )));
    }

    public function testItDoesNotCountTheSameOperationCacheInstanceTwice(): void
    {
        $metadata = new OperationCache(ttl: 300);

        self::assertSame($metadata, $this->extractor->extract(new Get(
            extraProperties: [OperationCache::class => $metadata, $metadata],
        )));
    }

    public function testItRejectsInvalidOperationCacheMetadata(): void
    {
        $operation = new Get(
            extraProperties: [
                OperationCache::class => 'invalid',
            ],
        );

        $this->expectException(InvalidOperationCacheMetadataException::class);
        $this->expectExceptionMessage(sprintf(
            'Extra property "%s" must be an instance of %s.',
            OperationCache::class,
            OperationCache::class,
        ));

        $this->extractor->extract($operation);
    }

    public function testItRejectsMultipleDistinctOperationCacheInstances(): void
    {
        $this->expectException(InvalidOperationCacheMetadataException::class);
        $this->expectExceptionMessage(sprintf(
            'Operation extra properties must contain at most one instance of %s.',
            OperationCache::class,
        ));

        $this->extractor->extract(new Get(extraProperties: [
            OperationCache::class => new OperationCache(ttl: 300),
            new OperationCache(ttl: 60),
        ]));
    }

    public function testOperationCacheCoexistsWithRepeatedInvalidationMetadata(): void
    {
        $cache = new OperationCache(ttl: 300);
        $first = new OperationCacheInvalidation(group: 'product:{id}');
        $second = new OperationCacheInvalidation(group: 'products');
        $operation = new Get(extraProperties: [$cache, $first, $second]);

        self::assertSame($cache, $this->extractor->extract($operation));
        self::assertSame(
            [$first, $second],
            $this->extractor->extractInvalidations($operation),
        );
    }

    public function testItExtractsRepeatedInvalidationMetadata(): void
    {
        $first = new OperationCacheInvalidation(group: 'product:{id}');
        $second = new OperationCacheInvalidation(group: 'products');
        self::assertSame(
            [$first, $second],
            $this->extractor->extractInvalidations(
                new Get(extraProperties: [$first, $second]),
            ),
        );
        self::assertSame([], $this->extractor->extractInvalidations(new Get()));
    }

    public function testItRejectsInvalidClassKeyedInvalidationMetadata(): void
    {
        $this->expectException(InvalidOperationCacheMetadataException::class);
        $this->extractor->extractInvalidations(new Get(extraProperties: [
            OperationCacheInvalidation::class => 'invalid',
        ]));
    }
}
