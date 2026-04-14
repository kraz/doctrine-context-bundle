<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Unit;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand as DoctrineMigrateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand as DoctrineValidateSchemaCommand;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Kraz\DoctrineContextBundle\Command\Doctrine\Strategy\ConnectionStrategy;
use Kraz\DoctrineContextBundle\Command\Doctrine\Strategy\EntityManagerStrategy;
use Kraz\DoctrineContextBundle\Command\Doctrine\Strategy\MigrationStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

use function class_exists;
use function interface_exists;

class CommandExecutionStrategyTest extends TestCase
{
    public function testEntityManagerStrategySupportsEntityManagerCommand(): void
    {
        if (! interface_exists(EntityManagerProvider::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

        $strategy = new EntityManagerStrategy();
        $command  = new DoctrineValidateSchemaCommand($this->createStub(EntityManagerProvider::class));

        self::assertTrue($strategy->supports($command));
    }

    public function testEntityManagerStrategyRejectsMigrationCommand(): void
    {
        if (! class_exists(DoctrineMigrateCommand::class)) {
            $this->markTestSkipped('doctrine/migrations is not installed');
        }

        $strategy = new EntityManagerStrategy();
        $command  = new DoctrineMigrateCommand($this->createStub(DependencyFactory::class));

        self::assertFalse($strategy->supports($command));
    }

    public function testEntityManagerStrategyRejectsPlainCommand(): void
    {
        $strategy = new EntityManagerStrategy();
        $command  = new Command('test:plain');

        self::assertFalse($strategy->supports($command));
    }

    public function testMigrationStrategySupportsDoctrineCommand(): void
    {
        if (! class_exists(DoctrineMigrateCommand::class)) {
            $this->markTestSkipped('doctrine/migrations is not installed');
        }

        $strategy = new MigrationStrategy();
        $command  = new DoctrineMigrateCommand($this->createStub(DependencyFactory::class));

        self::assertTrue($strategy->supports($command));
    }

    public function testMigrationStrategyRejectsEntityManagerCommand(): void
    {
        if (! interface_exists(EntityManagerProvider::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

        $strategy = new MigrationStrategy();
        $command  = new DoctrineValidateSchemaCommand($this->createStub(EntityManagerProvider::class));

        self::assertFalse($strategy->supports($command));
    }

    public function testMigrationStrategyRejectsPlainCommand(): void
    {
        $strategy = new MigrationStrategy();
        $command  = new Command('test:plain');

        self::assertFalse($strategy->supports($command));
    }

    public function testConnectionStrategySupportsCommandWithConnectionOption(): void
    {
        $strategy = new ConnectionStrategy();
        $command  = new Command('test:with-connection');
        $command->addOption('connection', null, InputOption::VALUE_OPTIONAL);

        self::assertTrue($strategy->supports($command));
    }

    public function testConnectionStrategyRejectsCommandWithoutConnectionOption(): void
    {
        $strategy = new ConnectionStrategy();
        $command  = new Command('test:no-connection');

        self::assertFalse($strategy->supports($command));
    }

    public function testConnectionStrategyRejectsEntityManagerCommand(): void
    {
        if (! interface_exists(EntityManagerProvider::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

        $strategy = new ConnectionStrategy();
        $command  = new DoctrineValidateSchemaCommand($this->createStub(EntityManagerProvider::class));

        self::assertFalse($strategy->supports($command));
    }

    public function testConnectionStrategyRejectsMigrationCommand(): void
    {
        if (! class_exists(DoctrineMigrateCommand::class)) {
            $this->markTestSkipped('doctrine/migrations is not installed');
        }

        $strategy = new ConnectionStrategy();
        $command  = new DoctrineMigrateCommand($this->createStub(DependencyFactory::class));

        self::assertFalse($strategy->supports($command));
    }
}
