<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverLocatorInterface;
use JacyImp\ApiPlatformOperationCache\Exception\UnsupportedVaryByException;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryBy;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByAuth;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByHeader;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryByResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final readonly class VaryByEvaluator
{
    public function __construct(
        private AuthIdentityResolverInterface $authIdentityResolver,
        private VaryResolverLocatorInterface $resolverLocator,
    ) {
    }

    /**
     * @param list<VaryBy> $definitions
     *
     * @return array<string, string>
     */
    public function evaluate(Request $request, array $definitions): array
    {
        $values = [];

        foreach ($definitions as $definition) {
            if ($definition instanceof VaryByHeader) {
                $header = strtolower($definition->header);

                $values[sprintf('header:%s', $header)] = json_encode(
                    $request->headers->all($definition->header),
                    JSON_THROW_ON_ERROR,
                );

                continue;
            }

            if ($definition instanceof VaryByAuth) {
                $identity = $this->authIdentityResolver->resolve();

                $values['auth'] = $identity === null
                    ? 'anonymous'
                    : sprintf('user:%s', $identity);

                continue;
            }

            if ($definition instanceof VaryByResolver) {
                $resolver = $this->resolverLocator->get(
                    $definition->resolver,
                );

                $values[sprintf(
                    'resolver:%s',
                    $definition->resolver,
                )] = $resolver->resolve($request);

                continue;
            }

            throw UnsupportedVaryByException::forDefinition($definition);
        }

        ksort($values);

        return $values;
    }
}
