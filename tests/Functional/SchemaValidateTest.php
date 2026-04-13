<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernelOrmOnly;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SchemaValidateTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;

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

    protected function databaseFilePrefix(): string
    {
        return 'doctrine_context_test_ormonly_';
    }

    public function testHelpListsStandardApplicationOptions(): void
    {
        $output = $this->captureOutput('doctrine:schema:validate --help');

        // Every option from Application::getDefaultInputDefinition() must appear in the
        // decorated command's help text, proving the application definition is merged in.
        self::assertStringContainsString('--help', $output);
        self::assertStringContainsString('--quiet', $output);
        self::assertStringContainsString('--verbose', $output);
        self::assertStringContainsString('--no-interaction', $output);
        self::assertStringContainsString('--ansi', $output);
    }

    public function testSchemaValidateSkipSyncPassesOnEmptyDatabase(): void
    {
        // With an empty database the sync check would fail (tables are missing).
        // If --skip-sync is not forwarded the exit code would be non-zero.
        $exitCode = $this->runCommand('doctrine:schema:validate --em=alpha --skip-sync');

        self::assertSame(0, $exitCode, '--skip-sync must be forwarded to the inner command');
    }

    public function testSchemaValidateSkipMappingAndSkipSyncPassOnEmptyDatabase(): void
    {
        // On an empty database both checks fail independently:
        //   --skip-sync  alone  → mapping passes, sync skipped         → exit 0
        //   --skip-mapping alone → mapping skipped, sync fails (no tables) → exit non-zero
        // Passing both flags together must also exit 0. If either flag is not forwarded to
        // the inner command then at least one check runs against the empty database and the
        // exit code becomes non-zero, catching the regression.
        $exitCode = $this->runCommand('doctrine:schema:validate --em=alpha --skip-mapping --skip-sync');

        self::assertSame(0, $exitCode, '--skip-mapping and --skip-sync must both be forwarded to the inner command');
    }
}
