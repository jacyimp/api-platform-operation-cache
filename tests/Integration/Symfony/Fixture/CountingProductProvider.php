<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * @implements ProviderInterface<CachedProduct|null>
 */
final class CountingProductProvider implements ProviderInterface
{
    public static int $calls = 0;

    /**
     * @param array<string, string> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?CachedProduct {
        ++self::$calls;

        $id = $uriVariables['id'] ?? null;

        if ($id === null) {
            return null;
        }

        return new CachedProduct(
            id: $id,
            value: sprintf(
                'provider-call-%d',
                self::$calls,
            ),
        );
    }

    public static function reset(): void
    {
        self::$calls = 0;
    }
}
