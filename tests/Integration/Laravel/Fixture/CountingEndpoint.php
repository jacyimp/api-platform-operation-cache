<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture;

use Illuminate\Http\JsonResponse;

final class CountingEndpoint
{
    public static int $calls = 0;

    public function __invoke(
        string $id,
    ): JsonResponse {
        ++self::$calls;

        return new JsonResponse([
            'id' => $id,
            'value' => sprintf(
                'endpoint-call-%d',
                self::$calls,
            ),
        ]);
    }

    public static function reset(): void
    {
        self::$calls = 0;
    }
}
