<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverLocatorInterface;
use JacyImp\ApiPlatformOperationCache\Core\VaryByEvaluator;
use JacyImp\ApiPlatformOperationCache\Exception\UnsupportedVaryByException;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryBy;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByAuth;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByHeader;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(VaryByEvaluator::class)]
final class VaryByEvaluatorTest extends TestCase
{
    public function testItEvaluatesHeaderVariation(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en');

        $evaluator = $this->evaluator();

        self::assertSame(
            [
                'header:accept-language' => '["en"]',
            ],
            $evaluator->evaluate(
                $request,
                [new VaryByHeader('Accept-Language')],
            ),
        );
    }

    public function testItNormalizesHeaderNames(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en');

        $evaluator = $this->evaluator();

        self::assertSame(
            $evaluator->evaluate(
                $request,
                [new VaryByHeader('Accept-Language')],
            ),
            $evaluator->evaluate(
                $request,
                [new VaryByHeader('accept-language')],
            ),
        );
    }

    public function testItPreservesMultipleHeaderValues(): void
    {
        $request = Request::create('/');
        $request->headers->set(
            'Accept',
            [
                'application/json',
                'application/ld+json',
            ],
        );

        $evaluator = $this->evaluator();

        self::assertSame(
            [
                'header:accept' => '["application/json","application/ld+json"]',
            ],
            $evaluator->evaluate(
                $request,
                [new VaryByHeader('Accept')],
            ),
        );
    }

    public function testItRepresentsMissingHeader(): void
    {
        $evaluator = $this->evaluator();

        self::assertSame(
            [
                'header:accept-language' => '[]',
            ],
            $evaluator->evaluate(
                Request::create('/'),
                [new VaryByHeader('Accept-Language')],
            ),
        );
    }

    public function testItEvaluatesAnonymousAuthVariation(): void
    {
        $evaluator = $this->evaluator(identity: null);

        self::assertSame(
            [
                'auth' => 'anonymous',
            ],
            $evaluator->evaluate(
                Request::create('/'),
                [new VaryByAuth()],
            ),
        );
    }

    public function testItEvaluatesAuthenticatedUserVariation(): void
    {
        $evaluator = $this->evaluator(identity: '42');

        self::assertSame(
            [
                'auth' => 'user:42',
            ],
            $evaluator->evaluate(
                Request::create('/'),
                [new VaryByAuth()],
            ),
        );
    }

    public function testItEvaluatesCustomResolverVariation(): void
    {
        $request = Request::create('/');
        $request->attributes->set('tenant', 'acme');

        $resolver = new TenantVaryResolver();

        $locator = new TestVaryResolverLocator([
            TenantVaryResolver::class => $resolver,
        ]);

        $evaluator = new VaryByEvaluator(
            new TestAuthIdentityResolver(null),
            $locator,
        );

        self::assertSame(
            [
                sprintf(
                    'resolver:%s',
                    TenantVaryResolver::class,
                ) => 'acme',
            ],
            $evaluator->evaluate(
                $request,
                [
                    new VaryByResolver(
                        TenantVaryResolver::class,
                    ),
                ],
            ),
        );
    }

    public function testItCombinesAndSortsVariationDimensions(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en');
        $request->attributes->set('tenant', 'acme');

        $locator = new TestVaryResolverLocator([
            TenantVaryResolver::class => new TenantVaryResolver(),
        ]);

        $evaluator = new VaryByEvaluator(
            new TestAuthIdentityResolver('42'),
            $locator,
        );

        self::assertSame(
            [
                'auth' => 'user:42',
                'header:accept-language' => '["en"]',
                sprintf(
                    'resolver:%s',
                    TenantVaryResolver::class,
                ) => 'acme',
            ],
            $evaluator->evaluate(
                $request,
                [
                    new VaryByResolver(
                        TenantVaryResolver::class,
                    ),
                    new VaryByHeader('Accept-Language'),
                    new VaryByAuth(),
                ],
            ),
        );
    }

    public function testVariationDefinitionOrderDoesNotMatter(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en');

        $evaluator = $this->evaluator(identity: '42');

        $first = $evaluator->evaluate(
            $request,
            [
                new VaryByAuth(),
                new VaryByHeader('Accept-Language'),
            ],
        );

        $second = $evaluator->evaluate(
            $request,
            [
                new VaryByHeader('Accept-Language'),
                new VaryByAuth(),
            ],
        );

        self::assertSame($first, $second);
    }

    public function testItRejectsUnsupportedVaryDefinition(): void
    {
        $evaluator = $this->evaluator();

        $varyBy = new UnsupportedVaryBy();

        $this->expectException(UnsupportedVaryByException::class);
        $this->expectExceptionMessage(sprintf(
            'Unsupported vary-by definition "%s".',
            UnsupportedVaryBy::class,
        ));

        $evaluator->evaluate(
            Request::create('/'),
            [$varyBy],
        );
    }

    private function evaluator(
        ?string $identity = null,
    ): VaryByEvaluator {
        return new VaryByEvaluator(
            new TestAuthIdentityResolver($identity),
            new TestVaryResolverLocator([]),
        );
    }
}

final readonly class TestAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function __construct(
        private ?string $identity,
    ) {
    }

    public function resolve(): ?string
    {
        return $this->identity;
    }
}

final readonly class TestVaryResolverLocator implements VaryResolverLocatorInterface
{
    /**
     * @param array<class-string<VaryResolverInterface>, VaryResolverInterface> $resolvers
     */
    public function __construct(
        private array $resolvers,
    ) {
    }

    public function get(string $resolver): VaryResolverInterface
    {
        return $this->resolvers[$resolver];
    }
}

final class TenantVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return (string) $request->attributes->get('tenant');
    }
}

final readonly class UnsupportedVaryBy implements VaryBy
{
}
