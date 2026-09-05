<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Metadata;

use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(VaryByResolver::class)]
final class VaryByResolverTest extends TestCase
{
    public function testItPreservesResolverClass(): void
    {
        $vary = new VaryByResolver(ExampleVaryResolver::class);

        self::assertSame(ExampleVaryResolver::class, $vary->resolver);
    }
}

final class ExampleVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return 'example';
    }
}
