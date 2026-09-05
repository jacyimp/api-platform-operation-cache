<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\KeyAnonymousAuthIdentityResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\KeyNeverCacheCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture\KeyTestContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CacheKeyGenerator::class)]
final class CacheKeyGeneratorTest extends TestCase
{
    public function testItGeneratesStableKeyForSameRequest(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_product');
        $cache = new OperationCache(ttl: 300);
        $request = Request::create(
            'https://example.com/products/42?page=2',
        );
        $request->setRequestFormat('json');

        self::assertSame(
            $generator->generate($operation, $request, $cache),
            $generator->generate($operation, $request, $cache),
        );
    }

    public function testQueryParameterOrderDoesNotAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');
        $cache = new OperationCache(ttl: 300);

        $first = Request::create(
            'https://example.com/products?page=2&category=books',
        );
        $second = Request::create(
            'https://example.com/products?category=books&page=2',
        );

        self::assertSame(
            $generator->generate($operation, $first, $cache),
            $generator->generate($operation, $second, $cache),
        );
    }

    public function testNestedQueryParameterOrderDoesNotAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');
        $cache = new OperationCache(ttl: 300);

        $first = Request::create(
            'https://example.com/products?filter[name]=foo&filter[type]=book',
        );
        $second = Request::create(
            'https://example.com/products?filter[type]=book&filter[name]=foo',
        );

        self::assertSame(
            $generator->generate($operation, $first, $cache),
            $generator->generate($operation, $second, $cache),
        );
    }

    public function testListOrderAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');
        $cache = new OperationCache(ttl: 300);

        $first = Request::create(
            'https://example.com/products?sort[]=price&sort[]=name',
        );
        $second = Request::create(
            'https://example.com/products?sort[]=name&sort[]=price',
        );

        self::assertNotSame(
            $generator->generate($operation, $first, $cache),
            $generator->generate($operation, $second, $cache),
        );
    }

    public function testPathAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_product');
        $cache = new OperationCache(ttl: 300);

        self::assertNotSame(
            $generator->generate(
                $operation,
                Request::create('https://example.com/products/1'),
                $cache,
            ),
            $generator->generate(
                $operation,
                Request::create('https://example.com/products/2'),
                $cache,
            ),
        );
    }

    public function testHostAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');
        $cache = new OperationCache(ttl: 300);

        self::assertNotSame(
            $generator->generate(
                $operation,
                Request::create('https://example.com/products'),
                $cache,
            ),
            $generator->generate(
                $operation,
                Request::create('https://other.example.com/products'),
                $cache,
            ),
        );
    }

    public function testRequestFormatAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');
        $cache = new OperationCache(ttl: 300);

        $json = Request::create('https://example.com/products');
        $json->setRequestFormat('json');

        $jsonLd = Request::create('https://example.com/products');
        $jsonLd->setRequestFormat('jsonld');

        self::assertNotSame(
            $generator->generate($operation, $json, $cache),
            $generator->generate($operation, $jsonLd, $cache),
        );
    }

    public function testOperationIdentityAffectsKey(): void
    {
        $generator = $this->generator();
        $request = Request::create('https://example.com/products');
        $cache = new OperationCache(ttl: 300);

        self::assertNotSame(
            $generator->generate(
                new Get(name: 'public_products'),
                $request,
                $cache,
            ),
            $generator->generate(
                new Get(name: 'admin_products'),
                $request,
                $cache,
            ),
        );
    }

    public function testVaryHeaderValuesAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');
        $cache = new OperationCache(
            ttl: 300,
            varyByHeaders: ['Accept-Language'],
        );

        $english = Request::create('https://example.com/products');
        $english->headers->set('Accept-Language', 'en');

        $french = Request::create('https://example.com/products');
        $french->headers->set('Accept-Language', 'fr');

        self::assertNotSame(
            $generator->generate($operation, $english, $cache),
            $generator->generate($operation, $french, $cache),
        );
    }

    public function testVaryHeaderOrderDoesNotAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');
        $request = Request::create('https://example.com/products');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept-Language', 'en');

        self::assertSame(
            $generator->generate(
                $operation,
                $request,
                new OperationCache(
                    ttl: 300,
                    varyByHeaders: ['Accept', 'Accept-Language'],
                ),
            ),
            $generator->generate(
                $operation,
                $request,
                new OperationCache(
                    ttl: 300,
                    varyByHeaders: ['Accept-Language', 'Accept'],
                ),
            ),
        );
    }

    public function testItReturnsNullWhenCacheConditionDoesNotMatch(): void
    {
        $generator = $this->generator([
            KeyNeverCacheCondition::class => new KeyNeverCacheCondition(),
        ]);

        self::assertNull($generator->generate(
            new Get(name: 'get_products'),
            Request::create('https://example.com/products'),
            new OperationCache(
                ttl: 300,
                when: KeyNeverCacheCondition::class,
            ),
        ));
    }

    public function testGeneratedKeyIsSafeAndCompact(): void
    {
        $key = $this->generator()->generate(
            new Get(name: 'get_products'),
            Request::create(
                'https://example.com/products?search=hello',
            ),
            new OperationCache(ttl: 300),
        );

        self::assertNotNull($key);
        self::assertMatchesRegularExpression(
            '/^api_platform_operation_cache\.v1\.[a-f0-9]{64}$/',
            $key,
        );
    }

    /**
     * @param array<string, object> $services
     */
    private function generator(array $services = []): CacheKeyGenerator
    {
        return new CacheKeyGenerator(
            new OperationCacheEvaluator(
                new KeyAnonymousAuthIdentityResolver(),
                new CacheStrategyRegistry(
                    new KeyTestContainer($services),
                ),
            ),
        );
    }
}
