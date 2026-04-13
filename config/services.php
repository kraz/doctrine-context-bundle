<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Kraz\DoctrineContextBundle\Command\Doctrine\Database\CreateDatabaseCommand;
use Kraz\DoctrineContextBundle\Configuration\Configuration as DoctrineContextConfiguration;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('doctrine.doctrine_context.configuration', DoctrineContextConfiguration::class)
            ->public()

        ->set('doctrine.database_create_command.with_context', CreateDatabaseCommand::class)
            ->decorate('doctrine.database_create_command', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:database:create']);
};
