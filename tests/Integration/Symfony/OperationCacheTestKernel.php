<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use JacyImp\ApiPlatformOperationCache\Symfony\ApiPlatformOperationCacheBundle;
use JacyImp\ApiPlatformOperationCache\Tests\Integration\Symfony\Fixture\CountingProductProvider;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

    public function registerContainerConfiguration(
        LoaderInterface $loader,
    ): void {
        $loader->load(
            static function (
                ContainerBuilder $container,
            ): void {
                $container->loadFromExtension(
                    'framework',
                    [
                        'secret' => 'operation-cache-test',
                        'test' => true,
                        'router' => [
                            'utf8' => true,
                        ],
                        'cache' => [
                            'app' => 'cache.adapter.array',
                        ],
                    ],
                );

                $container->loadFromExtension(
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

                $container
                    ->register(
                        CountingProductProvider::class,
                        CountingProductProvider::class,
                    )
                    ->setAutowired(true)
                    ->setAutoconfigured(true);
            },
        );
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
        return sys_get_temp_dir()
            . '/api-platform-operation-cache/'
            . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir()
            . '/api-platform-operation-cache/log';
    }
}
