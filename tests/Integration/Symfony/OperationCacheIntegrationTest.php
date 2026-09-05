<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony;

use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\CountingProductProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class OperationCacheIntegrationTest extends WebTestCase
{
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

        $content = $client
            ->getResponse()
            ->getContent();

        self::assertIsString($content);

        return $content;
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
}
