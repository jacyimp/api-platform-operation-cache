<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedResponse::class)]
final class CachedResponseTest extends TestCase
{
    public function testItPreservesResponseData(): void
    {
        $response = new CachedResponse(
            content: '{"id":42}',
            statusCode: 200,
            headers: [
                'content-type' => [
                    'application/json',
                ],
            ],
        );

        self::assertSame('{"id":42}', $response->content);
        self::assertSame(200, $response->statusCode);
        self::assertSame(
            [
                'content-type' => [
                    'application/json',
                ],
            ],
            $response->headers,
        );
    }
}
