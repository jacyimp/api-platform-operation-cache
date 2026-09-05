<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Exception;

use InvalidArgumentException;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryBy;

final class UnsupportedVaryByException extends InvalidArgumentException
{
    public static function forDefinition(VaryBy $varyBy): self
    {
        return new self(sprintf(
            'Unsupported vary-by definition "%s".',
            $varyBy::class,
        ));
    }
}
