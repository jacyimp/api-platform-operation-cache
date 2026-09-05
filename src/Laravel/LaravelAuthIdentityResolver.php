<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request as LaravelRequest;
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Exception\AuthIdentityResolutionException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class LaravelAuthIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        if (!$request instanceof LaravelRequest) {
            return null;
        }

        $user = $request->user();

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
