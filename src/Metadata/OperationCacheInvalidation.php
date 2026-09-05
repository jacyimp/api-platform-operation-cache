<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Metadata;

use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidationConditionInterface;
use JacyImp\ApiPlatformOperationCache\Contract\CacheInvalidationGroupResolverInterface;
use JacyImp\ApiPlatformOperationCache\Exception\InvalidOperationCacheException;

final readonly class OperationCacheInvalidation
{
    /**
     * @param class-string<CacheInvalidationConditionInterface>|null $when
     * @param class-string<CacheInvalidationGroupResolverInterface>|null $groupResolver
     */
    public function __construct(
        public ?string $group = null,
        public ?string $when = null,
        public ?string $groupResolver = null,
    ) {
        if ($group === null && $groupResolver === null) {
            throw new InvalidOperationCacheException(
                'Cache invalidation requires a group or group resolver.',
            );
        }

        if ($group !== null) {
            $this->assertGroup($group);
        }

        $this->assertOptionalService(
            $when,
            'Cache invalidation condition',
            CacheInvalidationConditionInterface::class,
        );
        $this->assertOptionalService(
            $groupResolver,
            'Cache invalidation group resolver',
            CacheInvalidationGroupResolverInterface::class,
        );
    }

    private function assertGroup(string $group): void
    {
        $group = trim($group);

        if ($group === '') {
            throw new InvalidOperationCacheException(
                'Cache invalidation group cannot be empty.',
            );
        }

        if (
            str_contains($group, '*')
            && $group !== '*'
            && !(
                str_ends_with($group, ':*')
                && substr_count($group, '*') === 1
            )
        ) {
            throw new InvalidOperationCacheException(sprintf(
                'Cache invalidation group "%s" must be exact, "*", or end in ":*".',
                $group,
            ));
        }
    }

    private function assertOptionalService(
        ?string $service,
        string $label,
        string $expectedType,
    ): void {
        if ($service !== null && trim($service) === '') {
            throw new InvalidOperationCacheException(sprintf(
                '%s cannot be empty.',
                $label,
            ));
        }

        if (
            $service !== null
            && class_exists($service)
            && !is_a($service, $expectedType, true)
        ) {
            throw new InvalidOperationCacheException(sprintf(
                '%s "%s" must implement %s.',
                $label,
                $service,
                $expectedType,
            ));
        }
    }
}
