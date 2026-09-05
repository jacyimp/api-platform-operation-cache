<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Core\CacheGroupGenerationManager;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupNormalizer;
use JacyImp\ApiPlatformOperationCache\Core\CacheInvalidator;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\GenerationTestCacheStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheGroupGenerationManager::class)]
#[CoversClass(CacheInvalidator::class)]
final class CacheInvalidatorTest extends TestCase
{
    public function testItCalculatesHierarchicalGenerationsInOneRead(): void
    {
        $store = new GenerationTestCacheStore();
        $manager = new CacheGroupGenerationManager($store);

        self::assertSame(
            [
                '*' => '0',
                'vendor:*' => '0',
                'vendor:12:*' => '0',
                'vendor:12:product:*' => '0',
                'vendor:12:product:42' => '0',
            ],
            $manager->generationsFor(['vendor:12:product:42']),
        );
        self::assertCount(5, $store->lastKeys);
    }

    public function testInvalidationChangesOnlySelectedGeneration(): void
    {
        $store = new GenerationTestCacheStore();
        $manager = new CacheGroupGenerationManager($store);
        $invalidator = new CacheInvalidator(new CacheGroupNormalizer(), $manager);

        self::assertSame([], $manager->generationsFor([]));
        $before = $manager->generationsFor(['product:1', 'product:2']);
        $invalidator->invalidateGroups(['product:1', 'product:1']);
        $after = $manager->generationsFor(['product:1', 'product:2']);

        self::assertNotSame($before['product:1'], $after['product:1']);
        self::assertSame($before['product:2'], $after['product:2']);
        self::assertSame($before['product:*'], $after['product:*']);
    }

    public function testPrefixAndGlobalInvalidationChangeDependencies(): void
    {
        $store = new GenerationTestCacheStore();
        $manager = new CacheGroupGenerationManager($store);
        $invalidator = new CacheInvalidator(new CacheGroupNormalizer(), $manager);
        $before = $manager->generationsFor(['product:1', 'vendor:2']);

        $invalidator->invalidateGroups(['product:*']);
        $prefix = $manager->generationsFor(['product:1', 'vendor:2']);
        self::assertNotSame($before['product:*'], $prefix['product:*']);
        self::assertSame($before['vendor:2'], $prefix['vendor:2']);

        $invalidator->invalidateGroups(['*']);
        $global = $manager->generationsFor(['product:1', 'vendor:2']);
        self::assertNotSame($prefix['*'], $global['*']);
    }
}
