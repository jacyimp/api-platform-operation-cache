<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

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

final class RegistryTestVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return 'test';
    }
}

final class RegistryTestCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return true;
    }
}
