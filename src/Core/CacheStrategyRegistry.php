<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final readonly class CacheStrategyRegistry
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param class-string<VaryResolverInterface> $resolver
     */
    public function varyResolver(string $resolver): VaryResolverInterface
    {
        return $this->get(
            $resolver,
            VaryResolverInterface::class,
        );
    }

    /**
     * @param class-string<AuthIdentityResolverInterface> $resolver
     */
    public function authIdentityResolver(
        string $resolver,
    ): AuthIdentityResolverInterface {
        return $this->get(
            $resolver,
            AuthIdentityResolverInterface::class,
        );
    }

    /**
     * @param class-string<CacheConditionInterface> $condition
     */
    public function condition(string $condition): CacheConditionInterface
    {
        return $this->get(
            $condition,
            CacheConditionInterface::class,
        );
    }

    /**
     * @param class-string<ResponseMutatorInterface> $mutator
     */
    public function responseMutator(
        string $mutator,
    ): ResponseMutatorInterface {
        return $this->get(
            $mutator,
            ResponseMutatorInterface::class,
        );
    }

    /**
     * @template T of object
     *
     * @param class-string $service
     * @param class-string<T> $expectedType
     *
     * @return T
     */
    private function get(
        string $service,
        string $expectedType,
    ): object {
        if (!$this->container->has($service)) {
            throw InvalidCacheStrategyException::notFound($service);
        }

        $resolved = $this->container->get($service);

        if (!$resolved instanceof $expectedType) {
            throw InvalidCacheStrategyException::invalidType(
                $service,
                $expectedType,
            );
        }

        return $resolved;
    }
}
