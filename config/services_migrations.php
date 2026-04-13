<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Migrations\Configuration\Configuration as DoctrineMigrationsConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\CurrentCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\DiffCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\DumpSchemaCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\ExecuteCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\GenerateCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\LatestCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\ListCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\MigrateCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\RollupCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\StatusCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\SyncMetadataCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\UpToDateCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\VersionCommand;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('doctrine.doctrine_context.connection_configuration', DoctrineMigrationsConfiguration::class)
            ->abstract()

        ->set('doctrine.doctrine_context.dependency_factory', DependencyFactory::class)
            ->abstract()

        ->set('doctrine_migrations.current_command.with_context', CurrentCommand::class)
            ->decorate('doctrine_migrations.current_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:current'])

        ->set('doctrine_migrations.diff_command.with_context', DiffCommand::class)
            ->decorate('doctrine_migrations.diff_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:diff'])

        ->set('doctrine_migrations.dump_schema_command.with_context', DumpSchemaCommand::class)
            ->decorate('doctrine_migrations.dump_schema_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:dump-schema'])

        ->set('doctrine_migrations.execute_command.with_context', ExecuteCommand::class)
            ->decorate('doctrine_migrations.execute_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:execute'])

        ->set('doctrine_migrations.generate_command.with_context', GenerateCommand::class)
            ->decorate('doctrine_migrations.generate_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:generate'])

        ->set('doctrine_migrations.latest_command.with_context', LatestCommand::class)
            ->decorate('doctrine_migrations.latest_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:latest'])

        ->set('doctrine_migrations.versions_command.with_context', ListCommand::class)
            ->decorate('doctrine_migrations.versions_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:list'])

        ->set('doctrine_migrations.migrate_command.with_context', MigrateCommand::class)
            ->decorate('doctrine_migrations.migrate_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:migrate'])

        ->set('doctrine_migrations.rollup_command.with_context', RollupCommand::class)
            ->decorate('doctrine_migrations.rollup_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:rollup'])

        ->set('doctrine_migrations.status_command.with_context', StatusCommand::class)
            ->decorate('doctrine_migrations.status_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:status'])

        ->set('doctrine_migrations.sync_metadata_command.with_context', SyncMetadataCommand::class)
            ->decorate('doctrine_migrations.sync_metadata_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:sync-metadata-storage'])

        ->set('doctrine_migrations.up_to_date_command.with_context', UpToDateCommand::class)
            ->decorate('doctrine_migrations.up_to_date_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:up-to-date'])

        ->set('doctrine_migrations.version_command.with_context', VersionCommand::class)
            ->decorate('doctrine_migrations.version_command')
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:migrations:version']);
};
