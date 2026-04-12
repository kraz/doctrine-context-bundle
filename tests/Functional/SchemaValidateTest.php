<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Kraz\DoctrineContextBundle\Tests\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function interface_exists;
use function sys_get_temp_dir;
use function unlink;

class SchemaValidateTest extends KernelTestCase
{
    private Application $application;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (! interface_exists(EntityManagerInterface::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

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

    private function runCommand(string $command): int
    {
        return $this->application->run(new StringInput($command), new BufferedOutput());
    }

    /**
     * Runs a command and returns its output as a string.
     *
     * PHPUnit sets SHELL_VERBOSITY=-1 to keep its own output clean, which causes
     * Symfony's Application to silence all command output. We temporarily override
     * that so the captured output reflects what a real terminal would see.
     */
    private function captureOutput(string $command): string
    {
        $previousEnv    = $_ENV['SHELL_VERBOSITY'] ?? null;
        $previousServer = $_SERVER['SHELL_VERBOSITY'] ?? null;

        $_ENV['SHELL_VERBOSITY']    = 0;
        $_SERVER['SHELL_VERBOSITY'] = 0;

        try {
            $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
            $this->application->run(new StringInput($command), $output);

            return $output->fetch();
        } finally {
            if ($previousEnv !== null) {
                $_ENV['SHELL_VERBOSITY'] = $previousEnv;
            } else {
                unset($_ENV['SHELL_VERBOSITY']);
            }

            if ($previousServer !== null) {
                $_SERVER['SHELL_VERBOSITY'] = $previousServer;
            } else {
                unset($_SERVER['SHELL_VERBOSITY']);
            }
        }
    }

    private function cleanDatabases(): void
    {
        foreach (['default', 'alpha', 'beta'] as $name) {
            $path = sys_get_temp_dir() . '/doctrine_context_test_' . $name . '.db';
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
