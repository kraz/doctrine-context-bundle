<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle;

use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Kraz\DoctrineContextBundle\Configuration\ExistingNamedConnection;
use Kraz\DoctrineContextBundle\Configuration\ExistingNamedEntityManager;
use Kraz\DoctrineContextBundle\EventListener\SchemaFilterListener;
use Override;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function array_filter;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function constant;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;

class DoctrineContextBundle extends AbstractBundle
{
    protected string $extensionAlias = 'doctrine_context';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $organizeMigrationModes = $this->getOrganizeMigrationsModes();
        $contextConfiguration   = static function (string $contextName) use ($organizeMigrationModes) {
            $node =  new ArrayNodeDefinition($contextName);
            $node
                ->arrayPrototype()
                    ->fixXmlConfig('migration', 'migrations')
                    ->fixXmlConfig('migrations_path', 'migrations_paths')
                    ->children()
                        ->arrayNode('migrations_paths')
                            ->info('A list of namespace/path pairs where to look for migrations.')
                            ->defaultValue([])
                            ->useAttributeAsKey('namespace')
                            ->prototype('scalar')->end()
                        ->end()

                        ->arrayNode('services')
                            ->info('A set of services to pass to the underlying doctrine/migrations library, allowing to change its behaviour.')
                            ->useAttributeAsKey('service')
                            ->defaultValue([])
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return count(array_filter(array_keys($v), static function (string $doctrineService): bool {
                                        return ! str_starts_with($doctrineService, 'Doctrine\Migrations\\');
                                    }));
                                })
                                ->thenInvalid('Valid services for the DoctrineMigrationsBundle must be in the "Doctrine\Migrations" namespace.')
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()

                        ->arrayNode('factories')
                            ->info('A set of callables to pass to the underlying doctrine/migrations library as services, allowing to change its behaviour.')
                            ->useAttributeAsKey('factory')
                            ->defaultValue([])
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return count(array_filter(array_keys($v), static function (string $doctrineService): bool {
                                        return ! str_starts_with($doctrineService, 'Doctrine\Migrations\\');
                                    }));
                                })
                                ->thenInvalid('Valid callables for the DoctrineMigrationsBundle must be in the "Doctrine\Migrations" namespace.')
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()

                        ->arrayNode('storage')
                            ->addDefaultsIfNotSet()
                            ->info('Storage to use for migration status metadata.')
                            ->children()
                                ->arrayNode('table_storage')
                                    ->addDefaultsIfNotSet()
                                    ->info('The default metadata storage, implemented as a table in the database.')
                                    ->children()
                                        ->scalarNode('table_name')->defaultValue('zzz_migrations')->cannotBeEmpty()->end()
                                        ->scalarNode('version_column_name')->defaultValue(null)->end()
                                        ->scalarNode('version_column_length')->defaultValue(null)->end()
                                        ->scalarNode('executed_at_column_name')->defaultValue(null)->end()
                                        ->scalarNode('execution_time_column_name')->defaultValue(null)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()

                        ->arrayNode('migrations')
                            ->info('A list of migrations to load in addition to the one discovered via "migrations_paths".')
                            ->prototype('scalar')->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('all_or_nothing')
                            ->info('Run all migrations in a transaction.')
                            ->defaultValue(false)
                        ->end()
                        ->scalarNode('check_database_platform')
                            ->info('Adds an extra check in the generated migrations to allow execution only on the same platform as they were initially generated on.')
                            ->defaultValue(true)
                        ->end()
                        ->scalarNode('custom_template')
                            ->info('Custom template path for generated migration classes.')
                            ->defaultValue(null)
                        ->end()
                        ->scalarNode('organize_migrations')
                            ->defaultValue(false)
                            ->info('Organize migrations mode. Possible values are: "BY_YEAR", "BY_YEAR_AND_MONTH", false')
                            ->validate()
                                ->ifTrue(static function ($v) use ($organizeMigrationModes): bool {
                                    if ($v === false) {
                                        return false;
                                    }

                                    return ! is_string($v) || ! in_array(strtoupper($v), $organizeMigrationModes, true);
                                })
                                ->thenInvalid('Invalid organize migrations mode value %s')
                            ->end()
                            ->validate()
                                ->ifString()
                                    ->then(static function ($v) {
                                        return constant('Doctrine\Migrations\Configuration\Configuration::VERSIONS_ORGANIZATION_' . strtoupper($v));
                                    })
                            ->end()
                        ->end()
                    ->end()
                ->end();

            return $node;
        };

        $definition->rootNode()
            ->children()
                ->append($contextConfiguration('entity_managers'))
                ->append($contextConfiguration('connections'))
            ->end();
    }

    /** @param array<string, mixed> $config */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        if (interface_exists('Doctrine\ORM\EntityManagerInterface')) {
            $container->import('../config/services_orm.php');
        }

        $contexts = array_values(array_unique(array_merge(array_keys($config['entity_managers'] ?? []), array_keys($config['connections'] ?? []))));
        foreach ($contexts as $context) {
            $entityManagerContext = $config['entity_managers'][$context] ?? null;
            $connectionContext    = $config['connections'][$context] ?? null;

            if ($entityManagerContext !== null && $connectionContext !== null) {
                throw new RuntimeException(sprintf('You can use either "entity_managers" or "connections" for doctrine context "%s", but not both!', $context));
            }

            if ($entityManagerContext !== null && ! interface_exists('Doctrine\ORM\EntityManagerInterface')) {
                throw new RuntimeException(sprintf('Cannot configure entity manager context "%s": doctrine/orm is not installed.', $context));
            }

            $this->loadContextConfiguration($context, $entityManagerContext ?? $connectionContext, $builder, $entityManagerContext !== null);
        }
    }

    /** @param array<string, mixed> $config */
    private function loadContextConfiguration(string $name, array $config, ContainerBuilder $builder, bool $isEntityManager): void
    {
        if (! isset($config['services'][MetadataStorage::class])) {
            $schemaFilterDefinition = $builder->setDefinition(sprintf('doctrine.doctrine_context.%s_events_listener.schema_filter', $name), new Definition(SchemaFilterListener::class));
            $schemaFilterDefinition
                ->addTag('doctrine.dbal.schema_filter', ['connection' => $name])
                ->addTag('kernel.event_listener', ['event' => 'console.command', 'method' => 'onConsoleCommand']);
        }

        if ($isEntityManager) {
            $configurationId            = sprintf('doctrine.doctrine_context.%s_entity_manager.configuration', $name);
            $configurationLoaderId      = sprintf('doctrine.doctrine_context.%s_entity_manager.configuration_loader', $name);
            $contextLoaderId            = sprintf('doctrine.doctrine_context.%s_em_loader', $name);
            $contextDependencyFactoryId = sprintf('doctrine.doctrine_context.%s_entity_manager.dependency_factory', $name);

            $builder
                ->register($contextLoaderId, ExistingNamedEntityManager::class)
                ->setArgument(0, $name)
                ->setArgument(1, new Reference(sprintf('doctrine.orm.%s_entity_manager', $name)));
        } else {
            $configurationId            = sprintf('doctrine.doctrine_context.%s_connection.configuration', $name);
            $configurationLoaderId      = sprintf('doctrine.doctrine_context.%s_connection.configuration_loader', $name);
            $contextLoaderId            = sprintf('doctrine.doctrine_context.%s_connection_loader', $name);
            $contextDependencyFactoryId = sprintf('doctrine.doctrine_context.%s_connection.dependency_factory', $name);

            $builder
                ->register($contextLoaderId, ExistingNamedConnection::class)
                ->setArgument(0, $name)
                ->setArgument(1, new Reference(sprintf('doctrine.dbal.%s_connection', $name)));
        }

        $configuration = $builder->setDefinition($configurationId, new ChildDefinition('doctrine.doctrine_context.connection_configuration'));
        $builder
            ->register($configurationLoaderId, ExistingConfiguration::class)
            ->addArgument(new Reference($configurationId));

        $diDefinition = $builder->setDefinition($contextDependencyFactoryId, new ChildDefinition('doctrine.doctrine_context.dependency_factory'));
        $diDefinition
            ->setFactory([DependencyFactory::class, $isEntityManager ? 'fromEntityManager' : 'fromConnection'])
            ->setArgument(0, new Reference($configurationLoaderId))
            ->setArgument(1, new Reference($contextLoaderId));

        foreach ($config['migrations_paths'] as $migrationNamespace => $migrationPath) {
            $migrationDirectory = $this->checkIfBundleRelativePath($migrationPath, $builder);
            $configuration->addMethodCall('addMigrationsDirectory', [$migrationNamespace, $migrationDirectory]);
        }

        foreach ($config['migrations'] as $migrationClass) {
            $configuration->addMethodCall('addMigrationClass', [$migrationClass]);
        }

        if ($config['organize_migrations'] !== false) {
            $configuration->addMethodCall('setMigrationOrganization', [$config['organize_migrations']]);
        }

        if ($config['custom_template'] !== null) {
            $configuration->addMethodCall('setCustomTemplate', [$config['custom_template']]);
        }

        $configuration->addMethodCall('setAllOrNothing', [$config['all_or_nothing']]);
        $configuration->addMethodCall('setCheckDatabasePlatform', [$config['check_database_platform']]);

        $builder
            ->getDefinition('doctrine.doctrine_context.configuration')
            ->addMethodCall('addDependencyFactory', [$name, new Reference($contextDependencyFactoryId)]);

        foreach ($config['services'] as $doctrineId => $symfonyId) {
            $diDefinition->addMethodCall('setDefinition', [$doctrineId, new ServiceClosureArgument(new Reference($symfonyId))]);
        }

        foreach ($config['factories'] as $doctrineId => $symfonyId) {
            $diDefinition->addMethodCall('setDefinition', [$doctrineId, new Reference($symfonyId)]);
        }

        if (! isset($config['services'][MetadataStorage::class])) {
            $filterDefinition     = $builder->getDefinition(sprintf('doctrine.doctrine_context.%s_events_listener.schema_filter', $name));
            $storageConfiguration = $config['storage']['table_storage'];

            $storageDefinition = new Definition(TableMetadataStorageConfiguration::class);
            $builder->setDefinition(sprintf('doctrine.doctrine_context.storage.%s_table_storage', $name), $storageDefinition);
            $builder->setAlias(sprintf('doctrine.doctrine_context.%s_metadata_storage', $name), sprintf('doctrine.doctrine_context.storage.%s_table_storage', $name));

            if ($storageConfiguration['table_name'] === null) {
                $filterDefinition->addArgument('doctrine_migration_versions');
            } else {
                $storageDefinition->addMethodCall('setTableName', [$storageConfiguration['table_name']]);
                $filterDefinition->addArgument($storageConfiguration['table_name']);
            }

            if ($storageConfiguration['version_column_name'] !== null) {
                $storageDefinition->addMethodCall('setVersionColumnName', [$storageConfiguration['version_column_name']]);
            }

            if ($storageConfiguration['version_column_length'] !== null) {
                $storageDefinition->addMethodCall('setVersionColumnLength', [$storageConfiguration['version_column_length']]);
            }

            if ($storageConfiguration['executed_at_column_name'] !== null) {
                $storageDefinition->addMethodCall('setExecutedAtColumnName', [$storageConfiguration['executed_at_column_name']]);
            }

            if ($storageConfiguration['execution_time_column_name'] !== null) {
                $storageDefinition->addMethodCall('setExecutionTimeColumnName', [$storageConfiguration['execution_time_column_name']]);
            }

            $configuration->addMethodCall('setMetadataStorageConfiguration', [new Reference(sprintf('doctrine.doctrine_context.storage.%s_table_storage', $name))]);
        }
    }

    private function checkIfBundleRelativePath(string $path, ContainerBuilder $builder): string
    {
        if (isset($path[0]) && $path[0] === '@') {
            $pathParts  = explode('/', $path);
            $bundleName = substr($pathParts[0], 1);

            $bundlePath = $this->getBundlePath($bundleName, $builder);

            return $bundlePath . substr($path, strlen('@' . $bundleName));
        }

        return $path;
    }

    private function getBundlePath(string $bundleName, ContainerBuilder $builder): string
    {
        $bundleMetadata = $builder->getParameter('kernel.bundles_metadata');
        assert(is_array($bundleMetadata));

        if (! isset($bundleMetadata[$bundleName])) {
            throw new RuntimeException(sprintf(
                'The bundle "%s" has not been registered, available bundles: %s',
                $bundleName,
                implode(', ', array_keys($bundleMetadata)),
            ));
        }

        return $bundleMetadata[$bundleName]['path'];
    }

    /**
     * Find organize migrations modes for their names.
     *
     * @return string[]
     */
    private function getOrganizeMigrationsModes(): array
    {
        $refClass    = new ReflectionClass('Doctrine\Migrations\Configuration\Configuration');
        $constsArray = $refClass->getConstants();
        $namesArray  = [];

        foreach ($constsArray as $key => $value) {
            if (! str_starts_with($key, 'VERSIONS_ORGANIZATION_')) {
                continue;
            }

            $namesArray[] = substr($key, strlen('VERSIONS_ORGANIZATION_'));
        }

        return $namesArray;
    }
}
