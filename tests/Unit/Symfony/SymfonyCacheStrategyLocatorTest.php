<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use JacyImp\ApiPlatformOperationCache\Symfony\SymfonyCacheStrategyLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SymfonyCacheStrategyLocator::class)]
final class SymfonyCacheStrategyLocatorTest extends TestCase
{
    public function testItIndexesStrategiesByClass(): void
    {
        $resolver = new LocatorTestVaryResolver();
        $condition = new LocatorTestCondition();

        $locator = new SymfonyCacheStrategyLocator(
            varyResolvers: [$resolver],
            authIdentityResolvers: [],
            conditions: [$condition],
            responseMutators: [],
        );

        self::assertTrue(
            $locator->has(
                LocatorTestVaryResolver::class,
            ),
        );
        self::assertTrue(
            $locator->has(
                LocatorTestCondition::class,
            ),
        );

        self::assertSame(
            $resolver,
            $locator->get(
                LocatorTestVaryResolver::class,
            ),
        );
        self::assertSame(
            $condition,
            $locator->get(
                LocatorTestCondition::class,
            ),
        );
    }

    public function testItRejectsUnknownStrategy(): void
    {
        $locator = new SymfonyCacheStrategyLocator(
            varyResolvers: [],
            authIdentityResolvers: [],
            conditions: [],
            responseMutators: [],
        );

        $this->expectException(
            InvalidCacheStrategyException::class,
        );

        $locator->get(
            LocatorTestVaryResolver::class,
        );
    }
}

final class LocatorTestVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return 'test';
    }
}

final class LocatorTestCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return true;
    }
}
