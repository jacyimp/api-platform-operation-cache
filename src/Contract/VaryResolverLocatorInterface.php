<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Contract;

interface VaryResolverLocatorInterface
{
    /**
     * @param class-string<VaryResolverInterface> $resolver
     */
    public function get(string $resolver): VaryResolverInterface;
}
