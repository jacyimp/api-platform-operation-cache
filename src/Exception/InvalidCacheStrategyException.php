<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Exception;

use InvalidArgumentException;

final class InvalidCacheStrategyException extends InvalidArgumentException
{
    public static function notFound(string $service): self
    {
        return new self(sprintf(
            'Cache strategy "%s" is not registered.',
            $service,
        ));
    }

    public static function invalidType(
        string $service,
        string $expectedType,
    ): self {
        return new self(sprintf(
            'Cache strategy "%s" must implement %s.',
            $service,
            $expectedType,
        ));
    }
}
