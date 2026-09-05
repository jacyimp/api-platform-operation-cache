<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony;

use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use JacyImp\ApiPlatformOperationCache\Symfony\SymfonyCacheStrategyLocator;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\Fixture\LocatorTestCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\Fixture\LocatorTestVaryResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
