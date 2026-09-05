<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use Psr\Container\ContainerInterface;

final readonly class EvaluatorTestContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
