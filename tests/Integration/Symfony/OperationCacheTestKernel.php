<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use JacyImp\ApiPlatformOperationCache\Symfony\ApiPlatformOperationCacheBundle;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\CountingProductProvider;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\NeverCacheCondition;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\RequestHeaderAuthIdentityResolver;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\TenantVaryResolver;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class OperationCacheTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ApiPlatformBundle();
        yield new ApiPlatformOperationCacheBundle();
    }

    protected function configureContainer(
        ContainerConfigurator $container,
    ): void {
        $container->extension(
            'framework',
            [
                'secret' => 'operation-cache-test',
                'test' => true,
                'cache' => [
                    'app' => 'cache.adapter.filesystem',
                ],
            ],
        );

        $container->extension(
            'api_platform',
            [
                'use_symfony_listeners' => true,
                'mapping' => [
                    'paths' => [
                        __DIR__ . '/Fixture',
                    ],
                ],
                'formats' => [
                    'json' => [
                        'application/json',
                    ],
                ],
            ],
        );

        $services = $container->services();

        foreach (
            [
                CountingProductProvider::class,
                NeverCacheCondition::class,
                RequestHeaderAuthIdentityResolver::class,
                TenantVaryResolver::class,
            ] as $service
        ) {
            $services
                ->set($service, $service)
                ->autowire()
                ->autoconfigure();
        }
    }

    protected function configureRoutes(
        RoutingConfigurator $routes,
    ): void {
        $routes
            ->import(
                '.',
                'api_platform',
            )
            ->prefix('/api');
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sprintf(
            '%s/api-platform-operation-cache/%d/%s',
            sys_get_temp_dir(),
            getmypid(),
            $this->environment,
        );
    }

    public function getLogDir(): string
    {
        return sprintf(
            '%s/api-platform-operation-cache/%d/log',
            sys_get_temp_dir(),
            getmypid(),
        );
    }
}
