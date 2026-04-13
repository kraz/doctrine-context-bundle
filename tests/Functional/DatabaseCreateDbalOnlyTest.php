<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernelDbalOnly;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies that doctrine:database:create fans out correctly when only DBAL is
 * configured — no ORM, no Migrations bundle registered.
 */
class DatabaseCreateDbalOnlyTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernelDbalOnly::class;
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

    public function testDatabaseCreateSingleContextViaConnectionOption(): void
    {
        $output = $this->captureOutput('doctrine:database:create --connection=alpha --if-not-exists');

        self::assertStringNotContainsString('default', $output, 'Output should not mention untargeted connections');
        self::assertStringNotContainsString('beta', $output, 'Output should not mention untargeted connections');
    }

    public function testDatabaseCreateSingleContextViaConnOption(): void
    {
        $output = $this->captureOutput('doctrine:database:create --conn=alpha --if-not-exists');

        self::assertStringNotContainsString('default', $output, 'Output should not mention untargeted connections');
        self::assertStringNotContainsString('beta', $output, 'Output should not mention untargeted connections');
    }

    public function testDatabaseCreateAllContextsFansOutAcrossAllRegisteredContexts(): void
    {
        $output = $this->captureOutput('doctrine:database:create --if-not-exists --ctx-isolation --no-interaction');

        self::assertStringContainsString('default', $output, 'Output should mention the default context');
        self::assertStringContainsString('alpha', $output, 'Output should mention the alpha context');
        self::assertStringContainsString('beta', $output, 'Output should mention the beta context');
    }

    protected function databaseFilePrefix(): string
    {
        return 'doctrine_context_test_dbalonly_';
    }
}
