<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Kraz\DoctrineContextBundle\Command\Doctrine\Database\CreateDatabaseCommand;
use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernel;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DatabaseCreateTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;

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
        $output = $this->captureOutput('doctrine:database:create --connection=alpha --if-not-exists');

        // No section header is emitted for a single context, so the connection name does not
        // appear in the output — but untargeted connections must not be processed at all.
        self::assertStringNotContainsString('default', $output, 'Output should not mention untargeted connections');
        self::assertStringNotContainsString('beta', $output, 'Output should not mention untargeted connections');
    }

    public function testDatabaseCreateSingleContextViaConnOption(): void
    {
        $output = $this->captureOutput('doctrine:database:create --conn=alpha --if-not-exists');

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
        $output = $this->captureOutput('doctrine:database:create --if-not-exists --ctx-isolation --no-interaction');

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
}
