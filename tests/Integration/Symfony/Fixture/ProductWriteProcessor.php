<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/** @implements ProcessorInterface<mixed, void> */
final class ProductWriteProcessor implements ProcessorInterface
{
    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): void {
    }
}
