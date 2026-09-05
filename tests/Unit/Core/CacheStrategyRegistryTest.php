<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\AffectedGroupsResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\NeverInvalidationCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\RegistryTestCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\RegistryTestVaryResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\RuntimeCacheGroupResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(CacheStrategyRegistry::class)]
final class CacheStrategyRegistryTest extends TestCase
{
    public function testItResolvesRegisteredStrategy(): void
    {
        $resolver = new RegistryTestVaryResolver();

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->with(RegistryTestVaryResolver::class)
            ->willReturn(true);
        $container
            ->method('get')
            ->with(RegistryTestVaryResolver::class)
            ->willReturn($resolver);

        $registry = new CacheStrategyRegistry($container);

        self::assertSame(
            $resolver,
            $registry->varyResolver(
                RegistryTestVaryResolver::class,
            ),
        );
    }

    public function testItRejectsMissingStrategy(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturn(false);

        $registry = new CacheStrategyRegistry($container);

        $this->expectException(
            InvalidCacheStrategyException::class,
        );
        $this->expectExceptionMessage(
            sprintf(
                'Cache strategy "%s" is not registered.',
                RegistryTestVaryResolver::class,
            ),
        );

        $registry->varyResolver(
            RegistryTestVaryResolver::class,
        );
    }

    public function testItResolvesGroupStrategies(): void
    {
        $services = [
            AffectedGroupsResolver::class => new AffectedGroupsResolver(),
            NeverInvalidationCondition::class => new NeverInvalidationCondition(),
            RuntimeCacheGroupResolver::class => new RuntimeCacheGroupResolver(),
        ];
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            static fn (string $id): object => $services[$id],
        );
        $registry = new CacheStrategyRegistry($container);

        self::assertSame(
            $services[RuntimeCacheGroupResolver::class],
            $registry->cacheGroupResolver(RuntimeCacheGroupResolver::class),
        );
        self::assertSame(
            $services[NeverInvalidationCondition::class],
            $registry->invalidationCondition(NeverInvalidationCondition::class),
        );
        self::assertSame(
            $services[AffectedGroupsResolver::class],
            $registry->invalidationGroupResolver(AffectedGroupsResolver::class),
        );
    }

    public function testItRejectsStrategyWithWrongType(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturn(true);
        $container
            ->method('get')
            ->willReturn(new RegistryTestCondition());

        $registry = new CacheStrategyRegistry($container);

        $this->expectException(
            InvalidCacheStrategyException::class,
        );
        $this->expectExceptionMessage(
            sprintf(
                'Cache strategy "%s" must implement %s.',
                RegistryTestVaryResolver::class,
                VaryResolverInterface::class,
            ),
        );

        $registry->varyResolver(
            RegistryTestVaryResolver::class,
        );
    }
}
