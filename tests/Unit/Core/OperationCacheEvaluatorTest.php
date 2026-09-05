<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheEvaluator;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(OperationCacheEvaluator::class)]
final class OperationCacheEvaluatorTest extends TestCase
{
    public function testItAppliesWithoutCondition(): void
    {
        self::assertSame(
            [],
            $this->evaluator()->evaluate(
                new OperationCache(ttl: 300),
                Request::create('/'),
            ),
        );
    }

    public function testItSkipsWhenConditionDoesNotMatch(): void
    {
        $evaluator = $this->evaluator([
            EvaluatorNeverCacheCondition::class => new EvaluatorNeverCacheCondition(),
        ]);

        self::assertNull($evaluator->evaluate(
            new OperationCache(
                ttl: 300,
                when: EvaluatorNeverCacheCondition::class,
            ),
            Request::create('/'),
        ));
    }

    public function testItVariesByRequestHeaders(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en');

        self::assertSame(
            ['header:accept-language' => '["en"]'],
            $this->evaluator()->evaluate(
                new OperationCache(
                    ttl: 300,
                    varyByHeaders: ['Accept-Language'],
                ),
                $request,
            ),
        );
    }

    public function testItUsesBuiltInAuthResolver(): void
    {
        self::assertSame(
            ['auth' => 'user:42'],
            $this->evaluator(defaultIdentity: '42')->evaluate(
                new OperationCache(
                    ttl: 300,
                    varyByAuth: true,
                ),
                Request::create('/'),
            ),
        );
    }

    public function testItRepresentsAnonymousAuth(): void
    {
        self::assertSame(
            ['auth' => 'anonymous'],
            $this->evaluator()->evaluate(
                new OperationCache(
                    ttl: 300,
                    varyByAuth: true,
                ),
                Request::create('/'),
            ),
        );
    }

    public function testItUsesCustomAuthResolver(): void
    {
        $evaluator = $this->evaluator([
            EvaluatorCustomAuthResolver::class => new EvaluatorCustomAuthResolver(),
        ]);

        self::assertSame(
            ['auth' => 'user:custom-user'],
            $evaluator->evaluate(
                new OperationCache(
                    ttl: 300,
                    varyByAuth: EvaluatorCustomAuthResolver::class,
                ),
                Request::create('/'),
            ),
        );
    }

    public function testItUsesCustomVaryResolver(): void
    {
        $request = Request::create('/');
        $request->attributes->set('tenant', 'acme');

        $evaluator = $this->evaluator([
            EvaluatorTenantVaryResolver::class => new EvaluatorTenantVaryResolver(),
        ]);

        self::assertSame(
            [sprintf('resolver:%s', EvaluatorTenantVaryResolver::class) => 'acme'],
            $evaluator->evaluate(
                new OperationCache(
                    ttl: 300,
                    varyByResolver: EvaluatorTenantVaryResolver::class,
                ),
                $request,
            ),
        );
    }

    public function testItSortsVariationDimensions(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en');
        $request->attributes->set('tenant', 'acme');

        $evaluator = $this->evaluator(
            services: [
                EvaluatorTenantVaryResolver::class => new EvaluatorTenantVaryResolver(),
            ],
            defaultIdentity: '42',
        );

        self::assertSame(
            [
                'auth' => 'user:42',
                'header:accept-language' => '["en"]',
                sprintf('resolver:%s', EvaluatorTenantVaryResolver::class) => 'acme',
            ],
            $evaluator->evaluate(
                new OperationCache(
                    ttl: 300,
                    varyByHeaders: ['Accept-Language'],
                    varyByAuth: true,
                    varyByResolver: EvaluatorTenantVaryResolver::class,
                ),
                $request,
            ),
        );
    }

    /**
     * @param array<string, object> $services
     */
    private function evaluator(
        array $services = [],
        ?string $defaultIdentity = null,
    ): OperationCacheEvaluator {
        return new OperationCacheEvaluator(
            new EvaluatorDefaultAuthResolver($defaultIdentity),
            new CacheStrategyRegistry(
                new EvaluatorTestContainer($services),
            ),
        );
    }
}

final readonly class EvaluatorDefaultAuthResolver implements AuthIdentityResolverInterface
{
    public function __construct(
        private ?string $identity,
    ) {
    }

    public function resolve(Request $request): ?string
    {
        return $this->identity;
    }
}

final class EvaluatorCustomAuthResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return 'custom-user';
    }
}

final class EvaluatorTenantVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return (string) $request->attributes->get('tenant');
    }
}

final class EvaluatorNeverCacheCondition implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return false;
    }
}

final readonly class EvaluatorTestContainer implements ContainerInterface
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
