<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Kraz\DoctrineContextBundle\DoctrineContextBundle;
use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

use function dirname;
use function sys_get_temp_dir;

class TestKernelDbalOnly extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /** @return iterable<BundleInterface> */
    #[Override]
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new DoctrineContextBundle(),
        ];
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
    }

    #[Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'default_connection' => 'default',
                    'connections' => [
                        'default' => [
                            'driver' => 'pdo_sqlite',
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_dbalonly_default.db',
                        ],
                        'alpha' => [
                            'driver' => 'pdo_sqlite',
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_dbalonly_alpha.db',
                        ],
                        'beta' => [
                            'driver' => 'pdo_sqlite',
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_dbalonly_beta.db',
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('doctrine_context', [
                'connections' => [
                    'default' => [],
                    'alpha'   => [],
                    'beta'    => [],
                ],
            ]);
        });
    }

    #[Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
}
