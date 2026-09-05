<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheMetadataException;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
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
        $operation = new Get();

        self::assertNull($this->extractor->extract($operation));
    }

    public function testItExtractsOperationCacheMetadata(): void
    {
        $metadata = new OperationCache(
            enabled: true,
            ttl: 300,
            vary: ['Accept-Language'],
        );

        $operation = new Get(
            extraProperties: [
                OperationCache::class => $metadata,
            ],
        );

        self::assertSame($metadata, $this->extractor->extract($operation));
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
}
