<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony;

use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\CountingProductProvider;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Response;

final class OperationCacheIntegrationTest extends WebTestCase
{
    private bool $symfonyErrorHandlerWasRegistered = false;

    #[Before]
    protected function captureExceptionHandlerStack(): void
    {
        $this->symfonyErrorHandlerWasRegistered = self::isSymfonyErrorHandlerRegistered();
    }

    #[After]
    protected function restoreExceptionHandlerStack(): void
    {
        if ($this->symfonyErrorHandlerWasRegistered || !self::isSymfonyErrorHandlerRegistered()) {
            return;
        }

        restore_exception_handler();
    }

    public function testSecondIdenticalRequestSkipsStateProvider(): void
    {
        $client = $this->createCacheClient();

        $first = $this->getJson(
            $client,
            '/api/cached-products/42',
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $first,
        );

        $second = $this->getJson(
            $client,
            '/api/cached-products/42',
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $second,
        );

        self::assertSame(
            1,
            CountingProductProvider::$calls,
        );
    }

    public function testDifferentUrisUseDifferentCacheEntries(): void
    {
        $client = $this->createCacheClient();

        $first = $this->getJson(
            $client,
            '/api/cached-products/42',
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $first,
        );

        $second = $this->getJson(
            $client,
            '/api/cached-products/43',
        );

        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $second,
        );

        $third = $this->getJson(
            $client,
            '/api/cached-products/42',
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $third,
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testDifferentQueryStringsUseDifferentCacheEntries(): void
    {
        $client = $this->createCacheClient();

        $first = $this->getJson(
            $client,
            '/api/cached-products/42?view=summary',
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $first,
        );

        $second = $this->getJson(
            $client,
            '/api/cached-products/42?view=detail',
        );

        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $second,
        );

        $third = $this->getJson(
            $client,
            '/api/cached-products/42?view=summary',
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $third,
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testFalseConditionBypassesCache(): void
    {
        $client = $this->createCacheClient();

        $first = $this->getJson(
            $client,
            '/api/conditionally-uncached-products/42',
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $first,
        );

        $second = $this->getJson(
            $client,
            '/api/conditionally-uncached-products/42',
        );

        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $second,
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testVaryByHeaderCreatesIndependentCacheEntries(): void
    {
        $client = $this->createCacheClient();

        $english = $this->getJson(
            $client,
            '/api/header-vary-products/42',
            [
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $english,
        );

        $french = $this->getJson(
            $client,
            '/api/header-vary-products/42',
            [
                'HTTP_ACCEPT_LANGUAGE' => 'fr',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $french,
        );

        $englishAgain = $this->getJson(
            $client,
            '/api/header-vary-products/42',
            [
                'HTTP_ACCEPT_LANGUAGE' => 'en',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $englishAgain,
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testVaryByAuthCreatesIndependentCacheEntries(): void
    {
        $client = $this->createCacheClient();

        $alice = $this->getJson(
            $client,
            '/api/auth-vary-products/42',
            [
                'HTTP_X_USER' => 'alice',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $alice,
        );

        $bob = $this->getJson(
            $client,
            '/api/auth-vary-products/42',
            [
                'HTTP_X_USER' => 'bob',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $bob,
        );

        $aliceAgain = $this->getJson(
            $client,
            '/api/auth-vary-products/42',
            [
                'HTTP_X_USER' => 'alice',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $aliceAgain,
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testCustomVaryResolverCreatesIndependentCacheEntries(): void
    {
        $client = $this->createCacheClient();

        $tenantA = $this->getJson(
            $client,
            '/api/resolver-vary-products/42',
            [
                'HTTP_X_TENANT' => 'tenant-a',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $tenantA,
        );

        $tenantB = $this->getJson(
            $client,
            '/api/resolver-vary-products/42',
            [
                'HTTP_X_TENANT' => 'tenant-b',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $tenantB,
        );

        $tenantAAgain = $this->getJson(
            $client,
            '/api/resolver-vary-products/42',
            [
                'HTTP_X_TENANT' => 'tenant-a',
            ],
        );

        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $tenantAAgain,
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testResponseMutatorRunsForCachedCopyAndCacheHit(): void
    {
        $client = $this->createCacheClient();

        $this->getJson(
            $client,
            '/api/response-mutator-products/42',
        );

        self::assertFalse(
            $this->getResponse($client)->headers->has(
                'X-Cached-Copy',
            ),
        );

        self::assertFalse(
            $this->getResponse($client)->headers->has(
                'X-Cache-Hit',
            ),
        );

        $this->getJson(
            $client,
            '/api/response-mutator-products/42',
        );

        $response = $this->getResponse($client);

        self::assertSame(
            'yes',
            $response->headers->get(
                'X-Cached-Copy',
            ),
        );

        self::assertSame(
            'yes',
            $response->headers->get(
                'X-Cache-Hit',
            ),
        );

        self::assertSame(
            'should-not-survive',
            $response->headers->get(
                'X-Excluded',
            ),
        );

        self::assertFalse(
            $response->headers->has('Age'),
        );

        self::assertFalse(
            $response->headers->has('Set-Cookie'),
        );

        self::assertSame(
            1,
            CountingProductProvider::$calls,
        );
    }

    public function testCustomResponseHeadersAreExcludedFromCachedResponse(): void
    {
        $client = $this->createCacheClient();

        $this->getJson(
            $client,
            '/api/response-exclusion-products/42',
        );

        $this->getJson(
            $client,
            '/api/response-exclusion-products/42',
        );

        $response = $this->getResponse($client);

        self::assertSame(
            'yes',
            $response->headers->get(
                'X-Cached-Copy',
            ),
        );

        self::assertFalse(
            $response->headers->has(
                'X-Excluded',
            ),
        );

        self::assertSame(
            1,
            CountingProductProvider::$calls,
        );
    }

    public function testDefaultResponseExclusionsCanBeDisabled(): void
    {
        $client = $this->createCacheClient();

        $this->getJson(
            $client,
            '/api/response-default-products/42',
        );

        $this->getJson(
            $client,
            '/api/response-default-products/42',
        );

        $response = $this->getResponse($client);

        self::assertSame(
            '60',
            $response->headers->get('Age'),
        );

        self::assertFalse(
            $response->headers->has(
                'Set-Cookie',
            ),
        );

        self::assertSame(
            1,
            CountingProductProvider::$calls,
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function getJson(
        KernelBrowser $client,
        string $uri,
        array $server = [],
    ): string {
        $client->request(
            method: 'GET',
            uri: $uri,
            server: [
                'HTTP_ACCEPT' => 'application/json',
                ...$server,
            ],
        );

        self::assertResponseIsSuccessful();

        $content = $this->getResponse($client)->getContent();

        self::assertIsString($content);

        return $content;
    }

    private function getResponse(KernelBrowser $client): Response
    {
        return $this->asHttpResponse($client->getResponse());
    }

    private function asHttpResponse(object $response): Response
    {
        if (!$response instanceof Response) {
            throw new \LogicException('The browser response must be an HTTP response.');
        }

        return $response;
    }

    private function createCacheClient(): KernelBrowser
    {
        CountingProductProvider::reset();

        $client = self::createClient();
        $client->disableReboot();

        $cache = self::getContainer()->get(
            'cache.app',
        );

        self::assertInstanceOf(
            CacheItemPoolInterface::class,
            $cache,
        );

        $cache->clear();

        return $client;
    }

    protected static function getKernelClass(): string
    {
        return OperationCacheTestKernel::class;
    }

    private static function isSymfonyErrorHandlerRegistered(): bool
    {
        $current = set_exception_handler(static fn () => null);
        restore_exception_handler();

        return \is_array($current) && $current[0] instanceof ErrorHandler;
    }
}
