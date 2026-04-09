<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Kraz\DoctrineContextBundle\EventListener\SchemaFilterListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

use function class_exists;

class SchemaFilterListenerTest extends TestCase
{
    public function testFilterDisabledByDefault(): void
    {
        $listener = new SchemaFilterListener('zzz_migrations');

        self::assertTrue($listener('zzz_migrations'));
        self::assertTrue($listener('some_other_table'));
    }

    public function testFilterEnabledOnUpdateCommand(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

        $listener = new SchemaFilterListener('zzz_migrations');
        $command  = new UpdateCommand($this->createStub(EntityManagerProvider::class));

        $listener->onConsoleCommand($this->createConsoleCommandEvent($command));

        self::assertFalse($listener('zzz_migrations'));
        self::assertTrue($listener('product'));
    }

    public function testFilterEnabledOnValidateSchemaCommand(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

        $listener = new SchemaFilterListener('zzz_migrations');
        $command  = new ValidateSchemaCommand($this->createStub(EntityManagerProvider::class));

        $listener->onConsoleCommand($this->createConsoleCommandEvent($command));

        self::assertFalse($listener('zzz_migrations'));
        self::assertTrue($listener('product'));
    }

    public function testFilterIgnoresUnrelatedCommands(): void
    {
        $listener = new SchemaFilterListener('zzz_migrations');
        $command  = new Command('some:unrelated:command');

        $listener->onConsoleCommand($this->createConsoleCommandEvent($command));

        self::assertTrue($listener('zzz_migrations'));
    }

    public function testFilterUsesConfiguredTableName(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

        $listener = new SchemaFilterListener('custom_migrations_table');
        $command  = new UpdateCommand($this->createStub(EntityManagerProvider::class));

        $listener->onConsoleCommand($this->createConsoleCommandEvent($command));

        self::assertFalse($listener('custom_migrations_table'));
        self::assertTrue($listener('zzz_migrations'));
    }

    private function createConsoleCommandEvent(Command $command): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());
    }
}
