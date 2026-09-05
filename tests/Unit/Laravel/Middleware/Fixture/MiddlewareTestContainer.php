<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel\Middleware\Fixture;

use Psr\Container\ContainerInterface;

final class MiddlewareTestContainer implements ContainerInterface
{
    public function get(string $id): object
    {
        throw new \LogicException(sprintf(
            'Unexpected strategy "%s".',
            $id,
        ));
    }

    public function has(string $id): bool
    {
        return false;
    }
}
