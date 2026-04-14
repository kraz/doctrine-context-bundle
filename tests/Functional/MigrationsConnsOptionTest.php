<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\SyncMetadataCommand;
use Kraz\DoctrineContextBundle\Tests\InspectsSqliteDatabasesTrait;
use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernelMigrationsOnly;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests the --conns multi-value option on migration commands, using the DBAL+migrations
 * kernel where all three contexts (default, alpha, beta) are registered as connections.
 */
class MigrationsConnsOptionTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;
    use InspectsSqliteDatabasesTrait;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernelMigrationsOnly::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->cleanDatabases();

        $kernel            = self::bootKernel();
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanDatabases();
    }

    protected function databaseFilePrefix(): string
    {
        return 'doctrine_context_test_dbal_';
    }

    public function testSyncMetadataWithConnsRunsOnlySelectedContexts(): void
    {
        $this->runCommand('doctrine:migrations:sync-metadata-storage --conns=alpha --conns=beta');

        $this->assertTableExists('alpha', 'zzz_migrations');
        $this->assertTableExists('beta', 'zzz_migrations');
        $this->assertTableNotExists('default', 'zzz_migrations');
    }

    public function testSyncMetadataWithConnsAcceptsCommaSeparatedValues(): void
    {
        $this->runCommand('doctrine:migrations:sync-metadata-storage --conns=alpha,beta');

        $this->assertTableExists('alpha', 'zzz_migrations');
        $this->assertTableExists('beta', 'zzz_migrations');
        $this->assertTableNotExists('default', 'zzz_migrations');
    }

    public function testSyncMetadataFailsWhenBothConnAndConnsAreSpecified(): void
    {
        $command = self::getContainer()->get('doctrine_migrations.sync_metadata_command.with_context');
        self::assertInstanceOf(SyncMetadataCommand::class, $command);
        /** @psalm-suppress InternalMethod */
        $command->mergeApplicationDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You can specify only one of the --conn and --conns options.');

        $input = new ArrayInput(['--conn' => 'alpha', '--conns' => ['beta']], $command->getDefinition());
        $command->run($input, new BufferedOutput());
    }

    #[Override]
    protected function getConnection(string $name): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.' . $name . '_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
