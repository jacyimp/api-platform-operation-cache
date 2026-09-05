<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony;

use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final class SymfonyCacheStrategyLocator implements ContainerInterface
{
    /** @var array<class-string, object> */
    private array $strategies = [];

    /**
     * @param iterable<object> $varyResolvers
     * @param iterable<object> $authIdentityResolvers
     * @param iterable<object> $conditions
     * @param iterable<object> $responseMutators
     */
    public function __construct(
        iterable $varyResolvers,
        iterable $authIdentityResolvers,
        iterable $conditions,
        iterable $responseMutators,
    ) {
        $this->register($varyResolvers);
        $this->register($authIdentityResolvers);
        $this->register($conditions);
        $this->register($responseMutators);
    }

    public function get(string $id): object
    {
        if (!isset($this->strategies[$id])) {
            throw InvalidCacheStrategyException::notFound($id);
        }

        return $this->strategies[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->strategies[$id]);
    }

    /**
     * @param iterable<object> $strategies
     */
    private function register(iterable $strategies): void
    {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy::class] = $strategy;
        }
    }
}
