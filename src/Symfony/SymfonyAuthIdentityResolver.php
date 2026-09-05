<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Exception\AuthIdentityResolutionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
final readonly class SymfonyAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function resolve(Request $request): ?string
    {
        $user = $this->tokenStorage
            ->getToken()
            ?->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        $identifier = trim($user->getUserIdentifier());

        if ($identifier === '') {
            throw new AuthIdentityResolutionException(
                'Authenticated user identifier cannot be empty.',
            );
        }

        return $identifier;
    }
}
