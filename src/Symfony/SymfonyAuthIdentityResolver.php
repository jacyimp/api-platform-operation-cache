<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SymfonyAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function resolve(): ?string
    {
        $user = $this->security->getUser();

        if ($user === null) {
            return null;
        }

        return $user->getUserIdentifier();
    }
}
