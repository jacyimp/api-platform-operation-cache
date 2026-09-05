<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Http;

use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CachedResponseFactory::class)]
final class CachedResponseFactoryTest extends TestCase
{
    public function testItCapturesResponseContentStatusAndHeaders(): void
    {
        $response = new Response(
            content: '{"id":42}',
            status: 201,
            headers: [
                'Content-Type' => 'application/json',
            ],
        );

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(ttl: 300),
        );

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

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(ttl: 300),
        );

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

    public function testItExcludesDefaultResponseHeaders(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'Age' => '10',
                'Date' => 'Sat, 05 Sep 2026 12:00:00 GMT',
                'Set-Cookie' => 'session=abc',
                'X-Keep-Me' => 'yes',
            ],
        );

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(ttl: 300),
        );

        self::assertArrayNotHasKey('age', $cached->headers);
        self::assertArrayNotHasKey('date', $cached->headers);
        self::assertArrayNotHasKey('set-cookie', $cached->headers);
        self::assertSame(
            ['yes'],
            $cached->headers['x-keep-me'],
        );
    }

    public function testDefaultResponseHeaderExclusionsCanBeDisabled(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'Age' => '10',
                'X-Keep-Me' => 'yes',
            ],
        );

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(
                ttl: 300,
                excludeDefaultResponseHeaders: false,
            ),
        );

        self::assertSame(
            ['10'],
            $cached->headers['age'],
        );
        self::assertSame(
            ['yes'],
            $cached->headers['x-keep-me'],
        );
    }

    public function testItExcludesCustomResponseHeaders(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'X-Request-Id' => 'abc',
                'X-Trace-Id' => 'def',
                'X-Keep-Me' => 'yes',
            ],
        );

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(
                ttl: 300,
                excludeResponseHeaders: [
                    'X-Request-Id',
                    'x-trace-id',
                ],
            ),
        );

        self::assertArrayNotHasKey(
            'x-request-id',
            $cached->headers,
        );
        self::assertArrayNotHasKey(
            'x-trace-id',
            $cached->headers,
        );
        self::assertSame(
            ['yes'],
            $cached->headers['x-keep-me'],
        );
    }

    public function testMandatoryHeadersRemainExcludedWhenDefaultsAreDisabled(): void
    {
        $response = new Response(
            content: '{}',
            headers: [
                'Connection' => 'keep-alive',
                'Content-Length' => '2',
                'Transfer-Encoding' => 'chunked',
            ],
        );

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(
                ttl: 300,
                excludeDefaultResponseHeaders: false,
            ),
        );

        self::assertArrayNotHasKey(
            'connection',
            $cached->headers,
        );
        self::assertArrayNotHasKey(
            'content-length',
            $cached->headers,
        );
        self::assertArrayNotHasKey(
            'transfer-encoding',
            $cached->headers,
        );
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

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(ttl: 300),
        );

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

    public function testWhenCachingMutatesOnlyTheCachedResponse(): void
    {
        $response = new Response(
            content: 'original',
            headers: [
                'X-Original' => 'yes',
            ],
        );

        $factory = $this->factory([
            TestResponseMutator::class => new TestResponseMutator(),
        ]);

        $cached = $factory->capture(
            $response,
            Request::create('/'),
            new OperationCache(
                ttl: 300,
                responseMutator: TestResponseMutator::class,
            ),
        );

        self::assertSame(
            'cached',
            $cached->content,
        );
        self::assertSame(
            ['yes'],
            $cached->headers['x-cached'],
        );

        self::assertSame(
            'original',
            $response->getContent(),
        );
        self::assertSame(
            'yes',
            $response->headers->get('X-Original'),
        );
        self::assertFalse(
            $response->headers->has('X-Cached'),
        );
    }

    public function testWhenServingCachedResponseCanMutateRestoredResponse(): void
    {
        $factory = $this->factory([
            TestResponseMutator::class => new TestResponseMutator(),
        ]);

        $response = $factory->restore(
            new CachedResponse(
                content: 'cached',
                statusCode: 200,
                headers: [],
            ),
            Request::create('/'),
            new OperationCache(
                ttl: 300,
                responseMutator: TestResponseMutator::class,
            ),
        );

        self::assertSame(
            'served',
            $response->getContent(),
        );
        self::assertSame(
            'yes',
            $response->headers->get('X-Cache-Hit'),
        );
    }

    public function testItRestoresCachedResponseWithoutMutator(): void
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

        $response = $this->factory()->restore(
            $cached,
            Request::create('/'),
            new OperationCache(ttl: 300),
        );

        self::assertSame(202, $response->getStatusCode());
        self::assertSame(
            '{"id":42}',
            $response->getContent(),
        );
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

        $cached = $this->factory()->capture(
            $response,
            Request::create('/'),
            new OperationCache(ttl: 300),
        );

        $keys = array_keys($cached->headers);
        $sorted = $keys;

        sort($sorted);

        self::assertSame($sorted, $keys);
    }

    /**
     * @param array<string, object> $services
     */
    private function factory(
        array $services = [],
    ): CachedResponseFactory {
        return new CachedResponseFactory(
            new CacheStrategyRegistry(
                new ResponseTestContainer($services),
            ),
        );
    }
}

final class TestResponseMutator implements ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response {
        $response->setContent('cached');
        $response->headers->remove('X-Original');
        $response->headers->set('X-Cached', 'yes');

        return $response;
    }

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response {
        $response->setContent('served');
        $response->headers->set('X-Cache-Hit', 'yes');

        return $response;
    }
}

final readonly class ResponseTestContainer implements ContainerInterface
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
