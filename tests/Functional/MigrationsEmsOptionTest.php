<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Kraz\DoctrineContextBundle\Command\Doctrine\Migrations\SyncMetadataCommand;
use Kraz\DoctrineContextBundle\Tests\InspectsSqliteDatabasesTrait;
use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests the --ems multi-value option on migration commands, using the ORM+migrations kernel
 * where all three contexts (default, alpha, beta) are registered as entity managers.
 */
class MigrationsEmsOptionTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;
    use InspectsSqliteDatabasesTrait;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
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
        return 'doctrine_context_test_';
    }

    public function testSyncMetadataWithEmsRunsOnlySelectedContexts(): void
    {
        $this->runCommand('doctrine:migrations:sync-metadata-storage --ems=alpha --ems=beta');

        $this->assertTableExists('alpha', 'zzz_migrations');
        $this->assertTableExists('beta', 'zzz_migrations');
        $this->assertTableNotExists('default', 'zzz_migrations');
    }

    public function testSyncMetadataWithEmsAcceptsCommaSeparatedValues(): void
    {
        $this->runCommand('doctrine:migrations:sync-metadata-storage --ems=alpha,beta');

        $this->assertTableExists('alpha', 'zzz_migrations');
        $this->assertTableExists('beta', 'zzz_migrations');
        $this->assertTableNotExists('default', 'zzz_migrations');
    }

    public function testSyncMetadataFailsWhenBothEmAndEmsAreSpecified(): void
    {
        $command = self::getContainer()->get('doctrine_migrations.sync_metadata_command.with_context');
        self::assertInstanceOf(SyncMetadataCommand::class, $command);
        /** @psalm-suppress InternalMethod */
        $command->mergeApplicationDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You can specify only one of the --em and --ems options.');

        $input = new ArrayInput(['--em' => 'alpha', '--ems' => ['beta']], $command->getDefinition());
        $command->run($input, new BufferedOutput());
    }

    #[Override]
    protected function getConnection(string $name): Connection
    {
        $registry = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        $connection = $registry->getConnection($name);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
