<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony;

use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\CountingProductProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class OperationCacheIntegrationTest extends WebTestCase
{
    protected function setUp(): void
    {
        CountingProductProvider::reset();
    }

    public function testSecondIdenticalRequestSkipsStateProvider(): void
    {
        $client = self::createClient();

        /*
         * Keep the same kernel alive between requests so the in-memory
         * cache.app pool survives the first request.
         */
        $client->disableReboot();

        $client->request(
            method: 'GET',
            uri: '/api/cached-products/42',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertJson(
            $client->getResponse()->getContent(),
        );
        self::assertStringContainsString(
            '"id":"42"',
            $client->getResponse()->getContent(),
        );
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
            'The state provider should not execute on a cache hit.',
        );
    }

    protected static function getKernelClass(): string
    {
        return OperationCacheTestKernel::class;
    }
}
