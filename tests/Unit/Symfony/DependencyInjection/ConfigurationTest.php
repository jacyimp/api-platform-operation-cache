<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Unit\Symfony\DependencyInjection;

use JacyImp\ApiPlatformOperationCache\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testItRejectsANonArrayConfigurationRoot(): void
    {
        $configuration = new Configuration();
        $method = new \ReflectionMethod($configuration, 'asArrayNode');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The configuration root node must be an array node.');

        $method->invoke(
            $configuration,
            new ScalarNodeDefinition('root'),
        );
    }
}
