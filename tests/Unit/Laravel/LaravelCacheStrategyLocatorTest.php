<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel;

use Illuminate\Contracts\Foundation\Application;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelCacheStrategyLocator;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel\Fixture\LaravelLocatorTestStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelCacheStrategyLocator::class)]
final class LaravelCacheStrategyLocatorTest extends TestCase
{
    public function testItResolvesBoundService(): void
    {
        $service = new LaravelLocatorTestStrategy();

        $application = $this->createMock(
            Application::class,
        );

        $application
            ->method('bound')
            ->with(LaravelLocatorTestStrategy::class)
            ->willReturn(true);

        $application
            ->method('make')
            ->with(LaravelLocatorTestStrategy::class)
            ->willReturn($service);

        $locator = new LaravelCacheStrategyLocator(
            $application,
        );

        self::assertTrue(
            $locator->has(
                LaravelLocatorTestStrategy::class,
            ),
        );

        self::assertSame(
            $service,
            $locator->get(
                LaravelLocatorTestStrategy::class,
            ),
        );
    }

    public function testConcreteClassIsConsideredResolvable(): void
    {
        $application = $this->createMock(
            Application::class,
        );

        $application
            ->method('bound')
            ->willReturn(false);

        $application
            ->method('make')
            ->with(LaravelLocatorTestStrategy::class)
            ->willReturn(
                new LaravelLocatorTestStrategy(),
            );

        $locator = new LaravelCacheStrategyLocator(
            $application,
        );

        self::assertTrue(
            $locator->has(
                LaravelLocatorTestStrategy::class,
            ),
        );

        self::assertInstanceOf(
            LaravelLocatorTestStrategy::class,
            $locator->get(
                LaravelLocatorTestStrategy::class,
            ),
        );
    }

    public function testItRejectsUnknownStrategy(): void
    {
        $application = $this->createMock(
            Application::class,
        );

        $application
            ->method('bound')
            ->willReturn(false);

        $locator = new LaravelCacheStrategyLocator(
            $application,
        );

        $this->expectException(
            InvalidCacheStrategyException::class,
        );

        $locator->get(
            'Unknown\\Cache\\Strategy',
        );
    }
}
