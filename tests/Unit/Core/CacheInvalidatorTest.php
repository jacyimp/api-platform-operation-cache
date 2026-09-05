<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Core\CacheGroupGenerationManager;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupNormalizer;
use JacyImp\ApiPlatformOperationCache\Core\CacheInvalidator;
use JacyImp\ApiPlatformOperationCache\Core\NullEventDispatcher;
use JacyImp\ApiPlatformOperationCache\Event\CacheGroupsInvalidatedEvent;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\GenerationTestCacheStore;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\RecordingEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheGroupGenerationManager::class)]
#[CoversClass(CacheInvalidator::class)]
#[CoversClass(NullEventDispatcher::class)]
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
        $dispatcher = new RecordingEventDispatcher();
        $invalidator = new CacheInvalidator(new CacheGroupNormalizer(), $manager, $dispatcher);

        self::assertSame([], $manager->generationsFor([]));
        $before = $manager->generationsFor(['product:1', 'product:2']);
        $invalidator->invalidateGroups(['product:1', 'product:1']);
        $after = $manager->generationsFor(['product:1', 'product:2']);

        self::assertNotSame($before['product:1'], $after['product:1']);
        self::assertSame($before['product:2'], $after['product:2']);
        self::assertSame($before['product:*'], $after['product:*']);
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(CacheGroupsInvalidatedEvent::class, $dispatcher->events[0]);
        self::assertSame(['product:1'], $dispatcher->events[0]->groups);
    }

    public function testItDispatchesOneEventForNormalizedInvalidationBatch(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $invalidator = new CacheInvalidator(
            new CacheGroupNormalizer(),
            new CacheGroupGenerationManager(new GenerationTestCacheStore()),
            $dispatcher,
        );

        $invalidator->invalidateGroups([
            ' product:42 ',
            'products',
            'product:42',
            'product:*',
            '*',
        ]);

        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(CacheGroupsInvalidatedEvent::class, $dispatcher->events[0]);
        self::assertSame(
            ['*', 'product:*', 'product:42', 'products'],
            $dispatcher->events[0]->groups,
        );
    }

    public function testEmptyInvalidationDoesNothing(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $invalidator = new CacheInvalidator(
            new CacheGroupNormalizer(),
            new CacheGroupGenerationManager(new GenerationTestCacheStore()),
            $dispatcher,
        );

        $invalidator->invalidateGroups([]);

        self::assertSame([], $dispatcher->events);
    }

    public function testNullDispatcherReturnsTheEvent(): void
    {
        $event = new \stdClass();

        self::assertSame($event, (new NullEventDispatcher())->dispatch($event));
    }

    public function testFailedGenerationWriteDoesNotDispatchInvalidationEvent(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $invalidator = new CacheInvalidator(
            new CacheGroupNormalizer(),
            new CacheGroupGenerationManager(new GenerationTestCacheStore(true)),
            $dispatcher,
        );

        try {
            $invalidator->invalidateGroups(['products']);
            self::fail('The generation write should fail.');
        } catch (\RuntimeException) {
            self::assertSame([], $dispatcher->events);
        }
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
