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

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $client->getResponse()->getContent(),
        );
        self::assertSame(
            1,
            CountingProductProvider::$calls,
        );

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $client->getResponse()->getContent(),
        );
        self::assertSame(
            1,
            CountingProductProvider::$calls,
        );
    }

    public function testDifferentUrisUseDifferentCacheEntries(): void
    {
        $client = $this->createCacheClient();

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $client->getResponse()->getContent(),
        );

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/43',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $client->getResponse()->getContent(),
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $client->getResponse()->getContent(),
        );
        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testDifferentQueryStringsUseDifferentCacheEntries(): void
    {
        $client = $this->createCacheClient();

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42?view=summary',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $client->getResponse()->getContent(),
        );

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42?view=detail',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $client->getResponse()->getContent(),
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42?view=summary',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $client->getResponse()->getContent(),
        );
        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    public function testFalseConditionBypassesCache(): void
    {
        $client = $this->createCacheClient();

        $client->request(
            method: 'GET',
            uri: '/api/conditionally-uncached-products/42',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-1"',
            $client->getResponse()->getContent(),
        );

        $client->request(
            method: 'GET',
            uri: '/api/conditionally-uncached-products/42',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            '"value":"provider-call-2"',
            $client->getResponse()->getContent(),
        );

        self::assertSame(
            2,
            CountingProductProvider::$calls,
        );
    }

    private function createCacheClient(): KernelBrowser
    {
        CountingProductProvider::reset();

        $client = self::createClient();
        $client->disableReboot();

        $cache = self::getContainer()->get('cache.app');

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
