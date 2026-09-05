<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core;

use JacyImp\ApiPlatformOperationCache\Core\AnonymousAuthIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AnonymousAuthIdentityResolver::class)]
final class AnonymousAuthIdentityResolverTest extends TestCase
{
    public function testItAlwaysResolvesAnonymousIdentity(): void
    {
        $resolver = new AnonymousAuthIdentityResolver();

        self::assertNull(
            $resolver->resolve(
                Request::create('/'),
            ),
        );
    }
}
