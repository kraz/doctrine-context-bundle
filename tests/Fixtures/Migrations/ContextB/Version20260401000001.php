<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Fixtures\Migrations\ContextB;

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260401000001 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create customer table for context B';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('customer');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('email', 'string', ['length' => 255]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $schema->dropTable('customer');
    }
}
