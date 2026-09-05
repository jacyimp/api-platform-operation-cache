<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel;

use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelServiceProvider::class)]
final class LaravelServiceProviderTest extends TestCase
{
    public function testItIsALaravelServiceProvider(): void
    {
        self::assertTrue(is_subclass_of(
            LaravelServiceProvider::class,
            ServiceProvider::class,
        ));
    }
}
