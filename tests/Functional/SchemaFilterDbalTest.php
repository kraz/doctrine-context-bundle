<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Kraz\DoctrineContextBundle\Tests\TestKernelDbal;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_column;
use function file_exists;
use function in_array;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

class SchemaFilterDbalTest extends KernelTestCase
{
    private Application $application;

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

    private function runCommand(string $command): int
    {
        return $this->application->run(new StringInput($command), new BufferedOutput());
    }

    private function getConnection(string $name): Connection
    {
        $connection = self::getContainer()->get(sprintf('doctrine.dbal.%s_connection', $name));
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    /**
     * Queries sqlite_master directly to bypass any active schema filters
     * that would hide tables like the migration metadata table.
     *
     * @return list<string>
     */
    private function getTableNames(string $connectionName): array
    {
        $rows = $this->getConnection($connectionName)->fetchAllAssociative(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
        );

        return array_column($rows, 'name');
    }

    private function assertTableExists(string $connectionName, string $tableName): void
    {
        self::assertTrue(
            in_array($tableName, $this->getTableNames($connectionName), true),
            'Table "' . $tableName . '" should exist in connection "' . $connectionName . '"',
        );
    }

    private function assertTableNotExists(string $connectionName, string $tableName): void
    {
        self::assertFalse(
            in_array($tableName, $this->getTableNames($connectionName), true),
            'Table "' . $tableName . '" should NOT exist in connection "' . $connectionName . '"',
        );
    }

    private function assertMigrationRecorded(string $connectionName, string $version): void
    {
        $count = $this->getConnection($connectionName)->fetchOne(
            'SELECT COUNT(*) FROM zzz_migrations WHERE version = ?',
            [$version],
        );

        self::assertGreaterThan(0, (int) $count, 'Migration "' . $version . '" should be recorded in connection "' . $connectionName . '"');
    }

    private function cleanDatabases(): void
    {
        foreach (['default', 'alpha', 'beta'] as $name) {
            $path = sys_get_temp_dir() . '/doctrine_context_test_dbal_' . $name . '.db';
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
