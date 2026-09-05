<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Http;

use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CachedResponseFactory::class)]
final class CachedResponseFactoryTest extends TestCase
{
    private CachedResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CachedResponseFactory();
    }

    public function testItCapturesResponseContentAndStatus(): void
    {
        $response = new Response(
            content: '{"id":42}',
            status: 201,
            headers: [
                'Content-Type' => 'application/json',
            ],
        );

        $cached = $this->factory->capture($response);

        self::assertSame('{"id":42}', $cached->content);
        self::assertSame(201, $cached->statusCode);
        self::assertSame(
            ['application/json'],
            $cached->headers['content-type'],
        );
    }

    public function testItPreservesApplicationResponseHeaders(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'Content-Type' => 'application/json',
                'Content-Language' => 'en',
                'ETag' => '"abc123"',
                'Link' => '</products?page=2>; rel="next"',
                'X-Custom-Header' => 'custom',
            ],
        );

        $cached = $this->factory->capture($response);

        self::assertSame(
            ['application/json'],
            $cached->headers['content-type'],
        );
        self::assertSame(
            ['en'],
            $cached->headers['content-language'],
        );
        self::assertSame(
            ['"abc123"'],
            $cached->headers['etag'],
        );
        self::assertSame(
            ['</products?page=2>; rel="next"'],
            $cached->headers['link'],
        );
        self::assertSame(
            ['custom'],
            $cached->headers['x-custom-header'],
        );
    }

    public function testItPreservesMultipleHeaderValues(): void
    {
        $response = new Response('{}');

        $response->headers->set(
            'Link',
            [
                '</products?page=2>; rel="next"',
                '</products?page=5>; rel="last"',
            ],
        );

        $cached = $this->factory->capture($response);

        self::assertSame(
            [
                '</products?page=2>; rel="next"',
                '</products?page=5>; rel="last"',
            ],
            $cached->headers['link'],
        );
    }

    public function testItExcludesVolatileAndTransportHeaders(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'Age' => '10',
                'Connection' => 'keep-alive',
                'Content-Length' => '2',
                'Date' => 'Sat, 05 Sep 2026 12:00:00 GMT',
                'Keep-Alive' => 'timeout=5',
                'Proxy-Authenticate' => 'Basic',
                'Proxy-Authorization' => 'Basic abc',
                'Set-Cookie' => 'session=abc',
                'TE' => 'trailers',
                'Trailer' => 'Expires',
                'Transfer-Encoding' => 'chunked',
                'Upgrade' => 'websocket',
            ],
        );

        $cached = $this->factory->capture($response);

        foreach (
            [
                'age',
                'connection',
                'content-length',
                'date',
                'keep-alive',
                'proxy-authenticate',
                'proxy-authorization',
                'set-cookie',
                'te',
                'trailer',
                'transfer-encoding',
                'upgrade',
            ] as $header
        ) {
            self::assertArrayNotHasKey(
                $header,
                $cached->headers,
            );
        }
    }

    public function testItExcludesHeadersNamedByConnectionHeader(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'Connection' => 'X-Transport-One, X-Transport-Two',
                'X-Transport-One' => 'one',
                'X-Transport-Two' => 'two',
                'X-Keep-Me' => 'yes',
            ],
        );

        $cached = $this->factory->capture($response);

        self::assertArrayNotHasKey(
            'x-transport-one',
            $cached->headers,
        );
        self::assertArrayNotHasKey(
            'x-transport-two',
            $cached->headers,
        );
        self::assertSame(
            ['yes'],
            $cached->headers['x-keep-me'],
        );
    }

    public function testItRestoresCachedResponse(): void
    {
        $cached = new CachedResponse(
            content: '{"id":42}',
            statusCode: 202,
            headers: [
                'content-type' => [
                    'application/json',
                ],
                'x-custom-header' => [
                    'foo',
                    'bar',
                ],
            ],
        );

        $response = $this->factory->restore($cached);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('{"id":42}', $response->getContent());
        self::assertSame(
            'application/json',
            $response->headers->get('Content-Type'),
        );
        self::assertSame(
            ['foo', 'bar'],
            $response->headers->all('X-Custom-Header'),
        );
    }

    public function testCapturedHeadersHaveDeterministicOrdering(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'X-Zebra' => 'z',
                'X-Alpha' => 'a',
            ],
        );

        $cached = $this->factory->capture($response);

        $keys = array_keys($cached->headers);
        $sorted = $keys;

        sort($sorted);

        self::assertSame($sorted, $keys);
    }
}
