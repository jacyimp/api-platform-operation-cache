<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony;

use JacyImp\ApiPlatformOperationCache\Exception\AuthIdentityResolutionException;
use JacyImp\ApiPlatformOperationCache\Symfony\SymfonyAuthIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(SymfonyAuthIdentityResolver::class)]
final class SymfonyAuthIdentityResolverTest extends TestCase
{
    public function testItReturnsNullForAnonymousRequest(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->method('getToken')
            ->willReturn(null);

        $resolver = new SymfonyAuthIdentityResolver($tokenStorage);

        self::assertNull($resolver->resolve(Request::create('/')));
    }

    public function testItResolvesAuthenticatedUserIdentifier(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user
            ->method('getUserIdentifier')
            ->willReturn('user-42');

        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $resolver = new SymfonyAuthIdentityResolver($tokenStorage);

        self::assertSame(
            'user-42',
            $resolver->resolve(Request::create('/')),
        );
    }

    public function testItRejectsEmptyAuthenticatedUserIdentifier(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user
            ->method('getUserIdentifier')
            ->willReturn('   ');

        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $resolver = new SymfonyAuthIdentityResolver($tokenStorage);

        $this->expectException(AuthIdentityResolutionException::class);
        $this->expectExceptionMessage(
            'Authenticated user identifier cannot be empty.',
        );

        $resolver->resolve(Request::create('/'));
    }
}
