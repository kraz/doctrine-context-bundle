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

class TestKernelExplicitContext extends Kernel implements CompilerPassInterface
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
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_explicit_default.db',
                        ],
                        'alpha' => [
                            'driver' => 'pdo_sqlite',
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_explicit_alpha.db',
                        ],
                        'beta' => [
                            'driver' => 'pdo_sqlite',
                            'path' => sys_get_temp_dir() . '/doctrine_context_test_explicit_beta.db',
                        ],
                    ],
                ],
                'orm' => [
                    'default_entity_manager' => 'default',
                    'entity_managers' => [
                        'default' => [
                            'connection' => 'default',
                            'mappings' => [
                                'Default' => [
                                    'is_bundle' => false,
                                    'type' => 'attribute',
                                    'dir' => __DIR__ . '/Fixtures/Entity/Default',
                                    'prefix' => 'Kraz\DoctrineContextBundle\Tests\Fixtures\Entity\Default',
                                ],
                            ],
                        ],
                        'alpha' => [
                            'connection' => 'alpha',
                            'mappings' => [
                                'ContextA' => [
                                    'is_bundle' => false,
                                    'type' => 'attribute',
                                    'dir' => __DIR__ . '/Fixtures/Entity/ContextA',
                                    'prefix' => 'Kraz\DoctrineContextBundle\Tests\Fixtures\Entity\ContextA',
                                ],
                            ],
                        ],
                        'beta' => [
                            'connection' => 'beta',
                            'mappings' => [
                                'ContextB' => [
                                    'is_bundle' => false,
                                    'type' => 'attribute',
                                    'dir' => __DIR__ . '/Fixtures/Entity/ContextB',
                                    'prefix' => 'Kraz\DoctrineContextBundle\Tests\Fixtures\Entity\ContextB',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('doctrine_migrations', [
                'migrations_paths' => [
                    'Kraz\DoctrineContextBundle\Tests\Fixtures\Migrations\Default' => __DIR__ . '/Fixtures/Migrations/Default',
                ],
                'storage' => [
                    'table_storage' => ['table_name' => 'zzz_migrations'],
                ],
            ]);

            $container->loadFromExtension('doctrine_context', [
                'explicit_context' => true,
                'entity_managers' => [
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
