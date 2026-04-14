<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Kraz\DoctrineContextBundle\Command\Doctrine\Schema\CreateSchemaCommand;
use Kraz\DoctrineContextBundle\Tests\InspectsSqliteDatabasesTrait;
use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernelOrmOnly;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class SchemaCreateTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;
    use InspectsSqliteDatabasesTrait;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernelOrmOnly::class;
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

    public function testSchemaCreateSingleContextCreatesTablesOnlyInTargetDatabase(): void
    {
        $exitCode = $this->runCommand('doctrine:schema:create --em=alpha');

        self::assertSame(0, $exitCode);

        $this->assertTableExists('alpha', 'product');
        $this->assertTableNotExists('alpha', 'tag');
        $this->assertTableNotExists('alpha', 'customer');

        $this->assertTableNotExists('default', 'tag');
        $this->assertTableNotExists('beta', 'customer');
    }

    public function testSchemaCreateAllContextsCreatesTablesInEachDatabase(): void
    {
        $exitCode = $this->runCommand('doctrine:schema:create');

        self::assertSame(0, $exitCode);

        $this->assertTableExists('default', 'tag');
        $this->assertTableNotExists('default', 'product');
        $this->assertTableNotExists('default', 'customer');

        $this->assertTableExists('alpha', 'product');
        $this->assertTableNotExists('alpha', 'tag');
        $this->assertTableNotExists('alpha', 'customer');

        $this->assertTableExists('beta', 'customer');
        $this->assertTableNotExists('beta', 'tag');
        $this->assertTableNotExists('beta', 'product');
    }

    public function testSchemaCreateDumpSqlDoesNotCreateTables(): void
    {
        // --dump-sql prints DDL statements instead of executing them.
        // If the flag is not forwarded to the inner command the schema is created
        // anyway, which the assertion below would catch.
        $exitCode = $this->runCommand('doctrine:schema:create --em=alpha --dump-sql');

        self::assertSame(0, $exitCode, '--dump-sql must be forwarded to the inner command');
        $this->assertTableNotExists('alpha', 'product');
    }

    public function testSchemaCreateDoesNotCreateMigrationTableInTargetDatabase(): void
    {
        $this->runCommand('doctrine:schema:create --em=alpha');

        $this->assertTableNotExists('alpha', 'zzz_migrations');
    }

    public function testSchemaCreateWithEmsRunsOnlySelectedContexts(): void
    {
        $exitCode = $this->runCommand('doctrine:schema:create --ems=alpha --ems=beta');

        self::assertSame(0, $exitCode);

        $this->assertTableExists('alpha', 'product');
        $this->assertTableExists('beta', 'customer');
        $this->assertTableNotExists('default', 'tag');
    }

    public function testSchemaCreateWithEmsAcceptsCommaSeparatedValues(): void
    {
        $exitCode = $this->runCommand('doctrine:schema:create --ems=alpha,beta');

        self::assertSame(0, $exitCode);

        $this->assertTableExists('alpha', 'product');
        $this->assertTableExists('beta', 'customer');
        $this->assertTableNotExists('default', 'tag');
    }

    public function testSchemaCreateFailsWhenBothEmAndEmsAreSpecified(): void
    {
        $command = self::getContainer()->get('doctrine.schema_create_command.with_context');
        self::assertInstanceOf(CreateSchemaCommand::class, $command);
        /** @psalm-suppress InternalMethod */
        $command->mergeApplicationDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You can specify only one of the --em and --ems options.');

        $input = new ArrayInput(['--em' => 'alpha', '--ems' => ['beta']], $command->getDefinition());
        $command->run($input, new BufferedOutput());
    }

    public function testSchemaValidateSucceedsAfterSchemaCreate(): void
    {
        $this->runCommand('doctrine:schema:create');

        $exitCode = $this->runCommand('doctrine:schema:validate');

        self::assertSame(0, $exitCode, 'Schema validation should succeed after schema:create');
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

    protected function databaseFilePrefix(): string
    {
        return 'doctrine_context_test_ormonly_';
    }
}
