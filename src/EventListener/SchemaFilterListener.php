<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\EventListener;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\AbstractNamedObject;
use Doctrine\DBAL\Schema\AbstractOptionallyNamedObject;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\Schema\ValidateSchemaCommand as ValidateSchemaContextCommand;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 * Acts as a schema filter that hides the migration metadata table except
 * when the execution context is that of command inside the migration's
 * namespace.
 *
 * @internal
 */
final class SchemaFilterListener
{
    private bool $enabled = false;

    public function __construct(private readonly string $configurationTableName)
    {
    }

    public function __invoke(AbstractAsset|AbstractNamedObject|AbstractOptionallyNamedObject|string $asset): bool
    {
        if (! $this->enabled) {
            return true;
        }

        if ($asset instanceof AbstractNamedObject) {
            $asset = $asset->getObjectName()->toString();
        }

        if ($asset instanceof AbstractOptionallyNamedObject) {
            $name = $asset->getObjectName()?->toString();
            if ($name === null) {
                return true;
            }

            $asset = $name;
        }

        if ($asset instanceof AbstractAsset) {
            /** @psalm-suppress DeprecatedMethod,InternalMethod */
            $asset = $asset->getName();
        }

        return $asset !== $this->configurationTableName;
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (
            ! $command instanceof ValidateSchemaCommand
            && ! $command instanceof UpdateCommand
            && ! $command instanceof ValidateSchemaContextCommand
        ) {
            return;
        }

        $this->enabled = true;
    }
}
