<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheException;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestInvalidationCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata\Fixture\TestInvalidationResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationCacheInvalidation::class)]
final class OperationCacheInvalidationTest extends TestCase
{
    public function testItAcceptsStaticResolverAndCombinedRules(): void
    {
        self::assertSame('products', (new OperationCacheInvalidation(group: 'products'))->group);
        self::assertSame(
            TestInvalidationResolver::class,
            (new OperationCacheInvalidation(
                groupResolver: TestInvalidationResolver::class,
            ))->groupResolver,
        );
        self::assertSame(
            TestInvalidationCondition::class,
            (new OperationCacheInvalidation(
                group: '*',
                when: TestInvalidationCondition::class,
            ))->when,
        );
        self::assertSame('product:*', (new OperationCacheInvalidation(group: 'product:*'))->group);
    }

    /** @return iterable<array{?string, ?string, ?string}> */
    public static function invalidRules(): iterable
    {
        yield [null, null, null];
        yield ['', null, null];
        yield ['foo*bar', null, null];
        yield ['products', '', null];
        yield [null, null, ''];
        yield ['products', \stdClass::class, null];
        yield [null, null, \stdClass::class];
    }

    #[DataProvider('invalidRules')]
    public function testItRejectsInvalidRules(?string $group, ?string $when, ?string $resolver): void
    {
        $this->expectException(InvalidOperationCacheException::class);
        (new \ReflectionClass(OperationCacheInvalidation::class))->newInstanceArgs([
            'group' => $group,
            'when' => $when,
            'groupResolver' => $resolver,
        ]);
    }
}
