<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Core\CacheGroupNormalizer;
use JacyImp\ApiPlatformOperationCache\Core\CacheGroupResolver;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\KeyTestContainer;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\RuntimeCacheGroupResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CacheGroupResolver::class)]
final class CacheGroupResolverTest extends TestCase
{
    public function testStaticAndRuntimeGroupsAreAdditive(): void
    {
        $request = Request::create('/products/42');
        $request->attributes->set('id', '42');
        $resolver = new CacheGroupResolver(
            new CacheGroupNormalizer(),
            new CacheStrategyRegistry(new KeyTestContainer([
                RuntimeCacheGroupResolver::class => new RuntimeCacheGroupResolver(),
            ])),
        );

        self::assertSame(
            ['product:42', 'products', 'vendor:12:products'],
            $resolver->resolve(
                new OperationCache(
                    ttl: 300,
                    groups: ['products', 'product:{id}'],
                    groupResolver: RuntimeCacheGroupResolver::class,
                ),
                $request,
            ),
        );
    }
}
