<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Laravel;

use Illuminate\Contracts\Foundation\Application;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheStrategyException;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final readonly class LaravelCacheStrategyLocator implements ContainerInterface
{
    public function __construct(
        private Application $application,
    ) {
    }

    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw InvalidCacheStrategyException::notFound($id);
        }

        $service = $this->application->make($id);

        if (!is_object($service)) {
            throw InvalidCacheStrategyException::notFound($id);
        }

        return $service;
    }

    public function has(string $id): bool
    {
        return $this->application->bound($id)
            || class_exists($id);
    }
}
