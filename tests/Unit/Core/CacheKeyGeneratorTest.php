<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverLocatorInterface;
use JacyImp\ApiPlatformOperationCache\Core\CacheKeyGenerator;
use JacyImp\ApiPlatformOperationCache\Core\VaryByEvaluator;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByHeader;
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

        $request = Request::create(
            'https://example.com/products/42?page=2',
        );
        $request->setRequestFormat('json');

        self::assertSame(
            $generator->generate($operation, $request),
            $generator->generate($operation, $request),
        );
    }

    public function testQueryParameterOrderDoesNotAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');

        $first = Request::create(
            'https://example.com/products?page=2&category=books',
        );

        $second = Request::create(
            'https://example.com/products?category=books&page=2',
        );

        self::assertSame(
            $generator->generate($operation, $first),
            $generator->generate($operation, $second),
        );
    }

    public function testNestedQueryParameterOrderDoesNotAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');

        $first = Request::create(
            'https://example.com/products'
            . '?filter[name]=foo&filter[type]=book',
        );

        $second = Request::create(
            'https://example.com/products'
            . '?filter[type]=book&filter[name]=foo',
        );

        self::assertSame(
            $generator->generate($operation, $first),
            $generator->generate($operation, $second),
        );
    }

    public function testListOrderAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');

        $first = Request::create(
            'https://example.com/products'
            . '?sort[]=price&sort[]=name',
        );

        $second = Request::create(
            'https://example.com/products'
            . '?sort[]=name&sort[]=price',
        );

        self::assertNotSame(
            $generator->generate($operation, $first),
            $generator->generate($operation, $second),
        );
    }

    public function testPathAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_product');

        self::assertNotSame(
            $generator->generate(
                $operation,
                Request::create('https://example.com/products/1'),
            ),
            $generator->generate(
                $operation,
                Request::create('https://example.com/products/2'),
            ),
        );
    }

    public function testHostAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');

        self::assertNotSame(
            $generator->generate(
                $operation,
                Request::create('https://example.com/products'),
            ),
            $generator->generate(
                $operation,
                Request::create('https://other.example.com/products'),
            ),
        );
    }

    public function testRequestFormatAffectsKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');

        $json = Request::create('https://example.com/products');
        $json->setRequestFormat('json');

        $jsonLd = Request::create('https://example.com/products');
        $jsonLd->setRequestFormat('jsonld');

        self::assertNotSame(
            $generator->generate($operation, $json),
            $generator->generate($operation, $jsonLd),
        );
    }

    public function testOperationIdentityAffectsKey(): void
    {
        $generator = $this->generator();
        $request = Request::create(
            'https://example.com/products',
        );

        self::assertNotSame(
            $generator->generate(
                new Get(name: 'public_products'),
                $request,
            ),
            $generator->generate(
                new Get(name: 'admin_products'),
                $request,
            ),
        );
    }

    public function testVaryValuesAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');

        $english = Request::create(
            'https://example.com/products',
        );
        $english->headers->set('Accept-Language', 'en');

        $french = Request::create(
            'https://example.com/products',
        );
        $french->headers->set('Accept-Language', 'fr');

        $varyBy = [
            new VaryByHeader('Accept-Language'),
        ];

        self::assertNotSame(
            $generator->generate(
                $operation,
                $english,
                $varyBy,
            ),
            $generator->generate(
                $operation,
                $french,
                $varyBy,
            ),
        );
    }

    public function testVaryDefinitionOrderDoesNotAffectKey(): void
    {
        $generator = $this->generator();
        $operation = new Get(name: 'get_products');

        $request = Request::create(
            'https://example.com/products',
        );
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept-Language', 'en');

        self::assertSame(
            $generator->generate(
                $operation,
                $request,
                [
                    new VaryByHeader('Accept'),
                    new VaryByHeader('Accept-Language'),
                ],
            ),
            $generator->generate(
                $operation,
                $request,
                [
                    new VaryByHeader('Accept-Language'),
                    new VaryByHeader('Accept'),
                ],
            ),
        );
    }

    public function testGeneratedKeyIsSafeAndCompact(): void
    {
        $key = $this->generator()->generate(
            new Get(name: 'get_products'),
            Request::create(
                'https://example.com/products?search=hello',
            ),
        );

        self::assertMatchesRegularExpression(
            '/^api_platform_operation_cache\.v1\.[a-f0-9]{64}$/',
            $key,
        );
    }

    private function generator(): CacheKeyGenerator
    {
        return new CacheKeyGenerator(
            new VaryByEvaluator(
                new AnonymousAuthIdentityResolver(),
                new EmptyVaryResolverLocator(),
            ),
        );
    }
}

final class AnonymousAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(): ?string
    {
        return null;
    }
}

final class EmptyVaryResolverLocator implements VaryResolverLocatorInterface
{
    public function get(string $resolver): VaryResolverInterface
    {
        throw new \LogicException(sprintf(
            'Resolver "%s" is not registered.',
            $resolver,
        ));
    }
}
