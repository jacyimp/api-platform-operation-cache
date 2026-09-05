<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class EvaluatorDefaultAuthResolver implements AuthIdentityResolverInterface
{
    public function __construct(
        private ?string $identity,
    ) {
    }

    public function resolve(Request $request): ?string
    {
        return $this->identity;
    }
}
