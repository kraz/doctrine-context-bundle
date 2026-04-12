<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Kraz\DoctrineContextBundle\Tests\InspectsSqliteDatabasesTrait;
use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernelDbal;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;

class SchemaFilterDbalTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;
    use InspectsSqliteDatabasesTrait;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernelDbal::class;
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

    public function testSingleContextMigratePreservesMigrationTable(): void
    {
        $this->runCommand('doctrine:migrations:migrate --conn=alpha --no-interaction');

        $this->assertTableExists('alpha', 'zzz_migrations');
        $this->assertMigrationRecorded('alpha', 'Kraz\\DoctrineContextBundle\\Tests\\Fixtures\\Migrations\\ContextA\\Version20260401000000');

        $this->assertTableNotExists('default', 'zzz_migrations');
        $this->assertTableNotExists('beta', 'zzz_migrations');
    }

    public function testAllContextsMigratePreservesMigrationTable(): void
    {
        $this->runCommand('doctrine:migrations:migrate --no-interaction');

        $this->assertTableExists('default', 'zzz_migrations');
        $this->assertMigrationRecorded('default', 'Kraz\\DoctrineContextBundle\\Tests\\Fixtures\\Migrations\\Default\\Version20260401000002');

        $this->assertTableExists('alpha', 'zzz_migrations');
        $this->assertMigrationRecorded('alpha', 'Kraz\\DoctrineContextBundle\\Tests\\Fixtures\\Migrations\\ContextA\\Version20260401000000');

        $this->assertTableExists('beta', 'zzz_migrations');
        $this->assertMigrationRecorded('beta', 'Kraz\\DoctrineContextBundle\\Tests\\Fixtures\\Migrations\\ContextB\\Version20260401000001');
    }

    public function testSingleContextMigrateOnlyAffectsTargetDatabase(): void
    {
        $this->runCommand('doctrine:migrations:migrate --conn=alpha --no-interaction');

        $this->assertTableExists('alpha', 'product');
        $this->assertTableNotExists('alpha', 'tag');
        $this->assertTableNotExists('alpha', 'customer');

        $this->assertTableNotExists('default', 'tag');
        $this->assertTableNotExists('default', 'zzz_migrations');

        $this->assertTableNotExists('beta', 'customer');
        $this->assertTableNotExists('beta', 'zzz_migrations');
    }

    public function testAllContextsMigrateIsolatesTablesPerDatabase(): void
    {
        $this->runCommand('doctrine:migrations:migrate --no-interaction');

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

    #[Override]
    protected function getConnection(string $name): Connection
    {
        $connection = self::getContainer()->get(sprintf('doctrine.dbal.%s_connection', $name));
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    protected function databaseFilePrefix(): string
    {
        return 'doctrine_context_test_dbal_';
    }
}
