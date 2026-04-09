<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Fixtures\Migrations\ContextA;

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260401000000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create product table for context A';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('product');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $schema->dropTable('product');
    }
}
