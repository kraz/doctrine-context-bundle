<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Kraz\DoctrineContextBundle\Command\Doctrine\Mapping\InfoCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Schema\ValidateSchemaCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('doctrine.mapping_info_command.with_context', InfoCommand::class)
            ->decorate('doctrine.mapping_info_command', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:mapping:info'])

        ->set('doctrine.schema_validate_command.with_context', ValidateSchemaCommand::class)
            ->decorate('doctrine.schema_validate_command', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([
                service('.inner'),
                service('doctrine.doctrine_context.configuration'),
            ])
            ->tag('console.command', ['command' => 'doctrine:schema:validate']);
};
