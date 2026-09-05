<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformOperationCache\Exception\AuthIdentityResolutionException;
use JacyImp\ApiPlatformOperationCache\Laravel\LaravelAuthIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelAuthIdentityResolver::class)]
final class LaravelAuthIdentityResolverTest extends TestCase
{
    public function testItReturnsNullForAnonymousRequest(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(
            static fn (): null => null,
        );

        self::assertNull(
            (new LaravelAuthIdentityResolver())->resolve($request),
        );
    }

    public function testItResolvesAuthenticatedUserIdentifier(): void
    {
        $user = $this->createMock(Authenticatable::class);
        $user
            ->method('getAuthIdentifier')
            ->willReturn(42);

        $request = Request::create('/');
        $request->setUserResolver(
            static fn (): Authenticatable => $user,
        );

        self::assertSame(
            '42',
            (new LaravelAuthIdentityResolver())->resolve($request),
        );
    }

    public function testItRejectsEmptyAuthenticatedUserIdentifier(): void
    {
        $user = $this->createMock(Authenticatable::class);
        $user
            ->method('getAuthIdentifier')
            ->willReturn('   ');

        $request = Request::create('/');
        $request->setUserResolver(
            static fn (): Authenticatable => $user,
        );

        $this->expectException(AuthIdentityResolutionException::class);
        $this->expectExceptionMessage(
            'Authenticated user identifier cannot be empty.',
        );

        (new LaravelAuthIdentityResolver())->resolve($request);
    }

    public function testItRejectsUnsupportedAuthenticatedUserIdentifier(): void
    {
        $user = $this->createMock(Authenticatable::class);
        $user
            ->method('getAuthIdentifier')
            ->willReturn(['invalid']);

        $request = Request::create('/');
        $request->setUserResolver(
            static fn (): Authenticatable => $user,
        );

        $this->expectException(AuthIdentityResolutionException::class);
        $this->expectExceptionMessage(
            'Authenticated user identifier must be a string or integer.',
        );

        (new LaravelAuthIdentityResolver())->resolve($request);
    }
}
