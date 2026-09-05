<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

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

    /** @return iterable<array{mixed}> */
    public static function invalidVaryHeaders(): iterable
    {
        yield ['invalid'];
        yield [[42]];
    }

    #[DataProvider('invalidVaryHeaders')]
    public function testItRejectsInvalidDefaultVaryConfiguration(mixed $headers): void
    {
        $config = $this->createMock(ConfigRepository::class);
        $config->method('get')->willReturn($headers);
        $app = $this->createMock(Application::class);
        $app->method('make')->with(ConfigRepository::class)->willReturn($config);
        $method = new ReflectionMethod(LaravelServiceProvider::class, 'defaultVaryByHeaders');

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke(null, $app);
    }
}
