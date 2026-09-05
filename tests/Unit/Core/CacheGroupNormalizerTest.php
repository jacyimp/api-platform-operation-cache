<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Core\CacheGroupNormalizer;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheGroupException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CacheGroupNormalizer::class)]
final class CacheGroupNormalizerTest extends TestCase
{
    public function testItNormalizesAndDeduplicatesGroups(): void
    {
        self::assertSame(
            ['product:42', 'products'],
            (new CacheGroupNormalizer())->memberships([
                ' products ',
                'product:42',
                'products',
            ]),
        );
    }

    public function testItInterpolatesAndEncodesUriVariables(): void
    {
        $request = Request::create('/products/a');
        $request->attributes->set('id', 'vendor:42/a');

        self::assertSame(
            'product:vendor%3A42%2Fa',
            (new CacheGroupNormalizer())->interpolate('product:{id}', $request),
        );
    }

    public function testItInterpolatesBooleanAndStringableValues(): void
    {
        $request = Request::create('/');
        $request->attributes->set('enabled', false);
        $request->attributes->set('value', new class implements Stringable {
            public function __toString(): string
            {
                return 'ok';
            }
        });

        self::assertSame(
            'flag:0:ok',
            (new CacheGroupNormalizer())->interpolate('flag:{enabled}:{value}', $request),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMemberships(): iterable
    {
        yield 'empty' => [' '];
        yield 'wildcard' => ['product:*'];
        yield 'placeholder' => ['product:{id}'];
        yield 'empty segment' => ['product::42'];
    }

    #[DataProvider('invalidMemberships')]
    public function testItRejectsInvalidMemberships(string $group): void
    {
        $this->expectException(InvalidCacheGroupException::class);
        (new CacheGroupNormalizer())->memberships([$group]);
    }

    public function testItAcceptsSupportedInvalidationTargets(): void
    {
        self::assertSame(
            ['*', 'product:*', 'product:42'],
            (new CacheGroupNormalizer())->invalidationTargets([
                'product:42',
                '*',
                'product:*',
            ]),
        );
    }

    /** @return iterable<int, array{string}> */
    public static function invalidTargets(): iterable
    {
        yield ['foo*bar'];
        yield ['vendor:*:product:42'];
        yield ['product:*:translations:*'];
        yield [':bad'];
        yield ['bad:'];
    }

    #[DataProvider('invalidTargets')]
    public function testItRejectsInvalidTargets(string $target): void
    {
        $this->expectException(InvalidCacheGroupException::class);
        (new CacheGroupNormalizer())->invalidationTargets([$target]);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidPlaceholderValues(): iterable
    {
        yield 'missing' => [null, 'missing'];
        yield 'empty' => ['', 'present'];
        yield 'array' => [[], 'present'];
    }

    #[DataProvider('invalidPlaceholderValues')]
    public function testItRejectsUnresolvablePlaceholders(mixed $value, string $mode): void
    {
        $request = Request::create('/');

        if ($mode === 'present') {
            $request->attributes->set('id', $value);
        }

        $this->expectException(InvalidCacheGroupException::class);
        (new CacheGroupNormalizer())->interpolate('product:{id}', $request);
    }

    public function testItRejectsMalformedPlaceholder(): void
    {
        $this->expectException(InvalidCacheGroupException::class);
        (new CacheGroupNormalizer())->interpolate('product:{bad-name}', Request::create('/'));
    }
}
