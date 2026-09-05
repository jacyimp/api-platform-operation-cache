<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel;

use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(LaravelServiceProvider::class)]
final class LaravelServiceProviderTest extends TestCase
{
    public function testItIsALaravelServiceProvider(): void
    {
        $parent = (new ReflectionClass(LaravelServiceProvider::class))
            ->getParentClass();

        self::assertInstanceOf(ReflectionClass::class, $parent);
        self::assertSame(ServiceProvider::class, $parent->getName());
    }
}
