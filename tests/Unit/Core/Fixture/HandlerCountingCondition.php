<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use Symfony\Component\HttpFoundation\Request;

final class HandlerCountingCondition implements CacheConditionInterface
{
    public int $calls = 0;

    public function matches(Request $request): bool
    {
        ++$this->calls;

        return true;
    }
}
