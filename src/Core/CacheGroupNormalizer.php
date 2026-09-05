<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Exception\InvalidCacheGroupException;
use Stringable;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class CacheGroupNormalizer
{
    /**
     * @param list<string> $groups
     *
     * @return list<string>
     */
    public function memberships(array $groups): array
    {
        return $this->normalize($groups, false);
    }

    /**
     * @param list<string> $groups
     *
     * @return list<string>
     */
    public function invalidationTargets(array $groups): array
    {
        return $this->normalize($groups, true);
    }

    public function interpolate(
        string $group,
        Request $request,
    ): string {
        $resolved = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $matches) use ($request): string {
                $name = $matches[1];

                if (!$request->attributes->has($name)) {
                    throw new InvalidCacheGroupException(sprintf(
                        'Cache group placeholder "{%s}" has no request attribute.',
                        $name,
                    ));
                }

                $value = $request->attributes->get($name);

                if (!is_scalar($value) && !$value instanceof Stringable) {
                    throw new InvalidCacheGroupException(sprintf(
                        'Cache group placeholder "{%s}" must resolve to a scalar or stringable value.',
                        $name,
                    ));
                }

                $value = is_bool($value)
                    ? ($value ? '1' : '0')
                    : (string) $value;

                if ($value === '') {
                    throw new InvalidCacheGroupException(sprintf(
                        'Cache group placeholder "{%s}" cannot resolve to an empty value.',
                        $name,
                    ));
                }

                return rawurlencode($value);
            },
            $group,
        );

        if ($resolved === null || str_contains($resolved, '{') || str_contains($resolved, '}')) {
            throw new InvalidCacheGroupException(sprintf(
                'Cache group "%s" contains an invalid or unresolved placeholder.',
                $group,
            ));
        }

        return $resolved;
    }

    /**
     * @param list<string> $groups
     *
     * @return list<string>
     */
    private function normalize(
        array $groups,
        bool $allowInvalidationWildcard,
    ): array {
        $normalized = [];

        foreach ($groups as $group) {
            $group = trim($group);
            $this->assertConcreteShape($group);

            if ($allowInvalidationWildcard) {
                $this->assertInvalidationWildcard($group);
            } elseif (str_contains($group, '*')) {
                throw new InvalidCacheGroupException(sprintf(
                    'Cache membership group "%s" cannot contain a wildcard.',
                    $group,
                ));
            }

            $normalized[$group] = true;
        }

        $groups = array_keys($normalized);
        sort($groups, SORT_STRING);

        return $groups;
    }

    private function assertConcreteShape(string $group): void
    {
        if ($group === '') {
            throw new InvalidCacheGroupException(
                'Cache group cannot be empty.',
            );
        }

        if (str_contains($group, '{') || str_contains($group, '}')) {
            throw new InvalidCacheGroupException(sprintf(
                'Cache group "%s" contains an invalid or unresolved placeholder.',
                $group,
            ));
        }

        if (
            $group !== '*'
            && (
                str_starts_with($group, ':')
                || str_ends_with($group, ':')
                || str_contains($group, '::')
            )
        ) {
            throw new InvalidCacheGroupException(sprintf(
                'Cache group "%s" contains an empty namespace segment.',
                $group,
            ));
        }
    }

    private function assertInvalidationWildcard(string $group): void
    {
        if (!str_contains($group, '*') || $group === '*') {
            return;
        }

        if (
            !str_ends_with($group, ':*')
            || substr_count($group, '*') !== 1
        ) {
            throw new InvalidCacheGroupException(sprintf(
                'Cache invalidation target "%s" must be exact, "*", or end in ":*".',
                $group,
            ));
        }
    }
}
