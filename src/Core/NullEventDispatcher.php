<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use Psr\EventDispatcher\EventDispatcherInterface;

/** @internal */
final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
