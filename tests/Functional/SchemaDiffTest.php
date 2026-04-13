<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Kraz\DoctrineContextBundle\Tests\InspectsSqliteDatabasesTrait;
use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_diff;
use function array_merge;
use function array_values;
use function file_exists;
use function file_get_contents;
use function glob;
use function unlink;

class SchemaDiffTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;
    use InspectsSqliteDatabasesTrait;

    /** @var list<string> migration files generated during the test, deleted in tearDown */
    private array $generatedMigrationFiles = [];

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->cleanDatabases();
        $this->generatedMigrationFiles = [];

        $kernel            = self::bootKernel();
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanDatabases();

        foreach ($this->generatedMigrationFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testDiffDoesNotIncludeMigrationsTableForSingleContext(): void
    {
        // Create only the migrations metadata table — no entity tables yet.
        // This simulates a fresh environment where doctrine:migrations:sync-metadata-storage
        // has been run but no entity migration has been executed yet.
        $this->runCommand('doctrine:migrations:sync-metadata-storage --em=alpha');

        $this->assertTableExists('alpha', 'zzz_migrations');
        $this->assertTableNotExists('alpha', 'product');

        $dir    = __DIR__ . '/../Fixtures/Migrations/ContextA';
        $glob   = glob($dir . '/Version*.php');
        $before = $glob !== false ? $glob : [];

        // Diff sees: DB has zzz_migrations only; ORM expects product.
        // Without the schema filter: generates DROP zzz_migrations + CREATE product.
        // With the schema filter:    generates CREATE product only.
        $this->runCommand('doctrine:migrations:diff --em=alpha --no-interaction');

        $glob                          = glob($dir . '/Version*.php');
        $newFiles                      = array_values(array_diff($glob !== false ? $glob : [], $before));
        $this->generatedMigrationFiles = $newFiles;

        self::assertCount(1, $newFiles, 'A migration file should have been generated for the missing entity schema');

        $content = (string) file_get_contents($newFiles[0]);
        self::assertStringContainsString('product', $content, 'The migration should create the product table');
        self::assertStringNotContainsString(
            'zzz_migrations',
            $content,
            'The migration metadata table must not appear in the generated diff migration',
        );
    }

    public function testDiffDoesNotIncludeMigrationsTableForAllContexts(): void
    {
        // Create only the migrations metadata tables for every context.
        $this->runCommand('doctrine:migrations:sync-metadata-storage --no-interaction');

        $dirs = [
            'default' => [__DIR__ . '/../Fixtures/Migrations/Default',   'tag'],
            'alpha'   => [__DIR__ . '/../Fixtures/Migrations/ContextA',  'product'],
            'beta'    => [__DIR__ . '/../Fixtures/Migrations/ContextB',  'customer'],
        ];

        $before = [];
        foreach ($dirs as $ctx => [$dir, $entityTable]) {
            $this->assertTableExists($ctx, 'zzz_migrations');
            $this->assertTableNotExists($ctx, $entityTable);
            $glob         = glob($dir . '/Version*.php');
            $before[$ctx] = $glob !== false ? $glob : [];
        }

        $this->runCommand('doctrine:migrations:diff --no-interaction');

        foreach ($dirs as $ctx => [$dir, $entityTable]) {
            $glob                          = glob($dir . '/Version*.php');
            $newFiles                      = array_values(array_diff($glob !== false ? $glob : [], $before[$ctx]));
            $this->generatedMigrationFiles = array_merge($this->generatedMigrationFiles, $newFiles);

            self::assertCount(1, $newFiles, "A migration file should have been generated for context '" . $ctx . "'");

            $content = (string) file_get_contents($newFiles[0]);
            self::assertStringContainsString(
                $entityTable,
                $content,
                "The migration for context '" . $ctx . "' should create the '" . $entityTable . "' table",
            );
            self::assertStringNotContainsString(
                'zzz_migrations',
                $content,
                "The migration metadata table must not appear in the diff for context '" . $ctx . "'",
            );
        }
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
