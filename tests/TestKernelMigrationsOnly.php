<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
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

class TestKernelMigrationsOnly extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /** @return iterable<BundleInterface> */
    #[Override]
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new DoctrineMigrationsBundle(),
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
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_dbal_default.db',
                        ],
                        'alpha' => [
                            'driver' => 'pdo_sqlite',
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_dbal_alpha.db',
                        ],
                        'beta' => [
                            'driver' => 'pdo_sqlite',
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_dbal_beta.db',
                        ],
                    ],
                ],
            ]);

            // doctrine_migrations base config targets the default connection.
            $container->loadFromExtension('doctrine_migrations', [
                'migrations_paths' => [
                    'Kraz\DoctrineContextBundle\Tests\Fixtures\Migrations\Default' => __DIR__ . '/Fixtures/Migrations/Default',
                ],
                'storage' => [
                    'table_storage' => ['table_name' => 'zzz_migrations'],
                ],
            ]);

            // doctrine_context registers all three connections as named contexts,
            // including "default" so that context-aware commands iterate over all three.
            $container->loadFromExtension('doctrine_context', [
                'connections' => [
                    'default' => [
                        'migrations_paths' => [
                            'Kraz\DoctrineContextBundle\Tests\Fixtures\Migrations\Default' => __DIR__ . '/Fixtures/Migrations/Default',
                        ],
                        'storage' => [
                            'table_storage' => ['table_name' => 'zzz_migrations'],
                        ],
                        'check_database_platform' => false,
                    ],
                    'alpha' => [
                        'migrations_paths' => [
                            'Kraz\DoctrineContextBundle\Tests\Fixtures\Migrations\ContextA' => __DIR__ . '/Fixtures/Migrations/ContextA',
                        ],
                        'storage' => [
                            'table_storage' => ['table_name' => 'zzz_migrations'],
                        ],
                        'check_database_platform' => false,
                    ],
                    'beta' => [
                        'migrations_paths' => [
                            'Kraz\DoctrineContextBundle\Tests\Fixtures\Migrations\ContextB' => __DIR__ . '/Fixtures/Migrations/ContextB',
                        ],
                        'storage' => [
                            'table_storage' => ['table_name' => 'zzz_migrations'],
                        ],
                        'check_database_platform' => false,
                    ],
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
