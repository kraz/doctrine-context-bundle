<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Kraz\DoctrineContextBundle\Command\Doctrine\Database\CreateDatabaseCommand;
use Kraz\DoctrineContextBundle\Tests\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function sys_get_temp_dir;
use function unlink;

class DatabaseCreateTest extends KernelTestCase
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
        $output = $this->runCommandCapturingOutput('doctrine:database:create --connection=alpha --if-not-exists');

        // No section header is emitted for a single context, so the connection name does not
        // appear in the output — but untargeted connections must not be processed at all.
        self::assertStringNotContainsString('default', $output, 'Output should not mention untargeted connections');
        self::assertStringNotContainsString('beta', $output, 'Output should not mention untargeted connections');
    }

    public function testDatabaseCreateSingleContextViaConnOption(): void
    {
        $output = $this->runCommandCapturingOutput('doctrine:database:create --conn=alpha --if-not-exists');

        // No section header is emitted for a single context, so the connection name does not
        // appear in the output — but untargeted connections must not be processed at all.
        self::assertStringNotContainsString('default', $output, 'Output should not mention untargeted connections');
        self::assertStringNotContainsString('beta', $output, 'Output should not mention untargeted connections');
    }

    public function testDatabaseCreateAllContextsFansOutAcrossAllRegisteredContexts(): void
    {
        // SQLite does not support createDatabase, so every context fails.
        // --ctx-isolation keeps the loop going after each failure instead of breaking.
        // --no-interaction suppresses the interactive "continue?" prompt that would
        // otherwise block waiting on stdin (walkDoctrineContexts asks before --ctx-isolation
        // is consulted as the fallback).
        $output = $this->runCommandCapturingOutput('doctrine:database:create --if-not-exists --ctx-isolation --no-interaction');

        self::assertStringContainsString('default', $output, 'Output should mention the default context');
        self::assertStringContainsString('alpha', $output, 'Output should mention the alpha context');
        self::assertStringContainsString('beta', $output, 'Output should mention the beta context');
    }

    public function testDatabaseCreateFailsWhenBothConnectionAndConnAreSpecified(): void
    {
        // Run through the command directly (bypassing Application) so that Symfony's
        // ConsoleEvents::ERROR listener never fires and no output is printed to stdout.
        $command = self::getContainer()->get('doctrine.database_create_command.with_context');
        self::assertInstanceOf(CreateDatabaseCommand::class, $command);
        /** @psalm-suppress InternalMethod */
        $command->mergeApplicationDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You can specify only one of the --connection and --conn options.');

        $input = new ArrayInput(['--connection' => 'alpha', '--conn' => 'alpha'], $command->getDefinition());
        $command->run($input, new BufferedOutput());
    }

    /**
     * Runs the command with normal verbosity by temporarily suspending the SHELL_VERBOSITY=-1
     * override that PHPUnit sets to keep its own output clean. Without this, the Symfony
     * Application resets the output to VERBOSITY_QUIET, silencing all command output.
     *
     * Symfony's Application::configureIO reads $_ENV first, then $_SERVER, then getenv().
     * PHPUnit sets $_SERVER['SHELL_VERBOSITY']=-1 via phpunit.xml.dist; overriding $_ENV
     * is sufficient to prevent the quiet-mode override.
     */
    private function runCommandCapturingOutput(string $command): string
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
