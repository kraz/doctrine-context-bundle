<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests;

use Doctrine\DBAL\Connection;

use function array_column;
use function in_array;

trait InspectsSqliteDatabasesTrait
{
    abstract protected function getConnection(string $name): Connection;

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
}
