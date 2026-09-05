<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use ApiPlatform\Metadata\Post;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupGenerationManager;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupNormalizer;
use JacyImp\ApiPlatformOperationCache\Core\CacheInvalidator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheInvalidator;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\AffectedGroupsResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\GenerationTestCacheStore;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\KeyTestContainer;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\NeverInvalidationCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(OperationCacheInvalidator::class)]
final class OperationCacheInvalidatorTest extends TestCase
{
    public function testItAppliesEachMatchingRuleAfterSuccess(): void
    {
        $store = new GenerationTestCacheStore();
        $manager = new CacheGroupGenerationManager($store);
        $normalizer = new CacheGroupNormalizer();
        $registry = new CacheStrategyRegistry(new KeyTestContainer([
            NeverInvalidationCondition::class => new NeverInvalidationCondition(),
            AffectedGroupsResolver::class => new AffectedGroupsResolver(),
        ]));
        $invalidator = new OperationCacheInvalidator(
            new OperationCacheMetadataExtractor(),
            $normalizer,
            $registry,
            new CacheInvalidator($normalizer, $manager),
        );
        $operation = new Post(extraProperties: [
            new OperationCacheInvalidation(group: 'product:{id}'),
            new OperationCacheInvalidation(group: 'ignored', when: NeverInvalidationCondition::class),
            new OperationCacheInvalidation(group: 'products', groupResolver: AffectedGroupsResolver::class),
        ]);
        $request = Request::create('/products/42/publish', 'POST');
        $request->attributes->set('id', '42');
        $before = $manager->generationsFor(['product:42', 'products', 'vendor:12:products', 'ignored']);

        $invalidator->invalidate($operation, $request, new Response('{}', 200));
        $after = $manager->generationsFor(['product:42', 'products', 'vendor:12:products', 'ignored']);

        self::assertNotSame($before['product:42'], $after['product:42']);
        self::assertNotSame($before['products'], $after['products']);
        self::assertNotSame($before['vendor:12:products'], $after['vendor:12:products']);
        self::assertSame($before['ignored'], $after['ignored']);
    }

    public function testItDoesNothingForFailedResponseOrOperationWithoutRules(): void
    {
        $store = new GenerationTestCacheStore();
        $manager = new CacheGroupGenerationManager($store);
        $normalizer = new CacheGroupNormalizer();
        $invalidator = new OperationCacheInvalidator(
            new OperationCacheMetadataExtractor(),
            $normalizer,
            new CacheStrategyRegistry(new KeyTestContainer([])),
            new CacheInvalidator($normalizer, $manager),
        );
        $request = Request::create('/', 'POST');
        $before = $manager->generationsFor(['products']);

        $invalidator->invalidate(
            new Post(extraProperties: [new OperationCacheInvalidation(group: 'products')]),
            $request,
            new Response('{}', 500),
        );
        $invalidator->invalidate(new Post(), $request, new Response('{}'));

        self::assertSame($before, $manager->generationsFor(['products']));
    }
}
