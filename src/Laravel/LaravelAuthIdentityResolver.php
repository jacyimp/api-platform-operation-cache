<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Exception\AuthIdentityResolutionException;

/**
 * @internal
 */
final readonly class LaravelAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): ?string
    {
        $user = $this->request->user();

        if (!$user instanceof Authenticatable) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();

        if (!is_int($identifier) && !is_string($identifier)) {
            throw new AuthIdentityResolutionException(
                'Authenticated user identifier must be a string or integer.',
            );
        }

        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            throw new AuthIdentityResolutionException(
                'Authenticated user identifier cannot be empty.',
            );
        }

        return $identifier;
    }
}
