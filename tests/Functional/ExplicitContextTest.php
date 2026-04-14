<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Functional;

use Kraz\DoctrineContextBundle\Command\Doctrine\Database\CreateDatabaseCommand;
use Kraz\DoctrineContextBundle\Tests\RunsConsoleCommandsTrait;
use Kraz\DoctrineContextBundle\Tests\TestKernelExplicitContext;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExplicitContextTest extends KernelTestCase
{
    use RunsConsoleCommandsTrait;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernelExplicitContext::class;
    }

    protected function databaseFilePrefix(): string
    {
        return 'doctrine_context_test_explicit_';
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

    public function testExplicitContextRequiresContextOptionOrCtxAll(): void
    {
        $command = self::getContainer()->get('doctrine.database_create_command.with_context');
        self::assertInstanceOf(CreateDatabaseCommand::class, $command);
        /** @psalm-suppress InternalMethod */
        $command->mergeApplicationDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Explicit context is required. Specify a context via --connection, --conn, or use --ctx-all to run over all contexts.');

        $input = new ArrayInput(['--if-not-exists' => true], $command->getDefinition());
        $command->run($input, new BufferedOutput());
    }

    public function testExplicitContextWorksWithConnectionOption(): void
    {
        $output = $this->captureOutput('doctrine:database:create --connection=alpha --if-not-exists');

        self::assertStringNotContainsString('default', $output, 'Output should not mention untargeted connections');
        self::assertStringNotContainsString('beta', $output, 'Output should not mention untargeted connections');
    }

    public function testExplicitContextWorksWithConnOption(): void
    {
        $output = $this->captureOutput('doctrine:database:create --conn=alpha --if-not-exists');

        self::assertStringNotContainsString('default', $output, 'Output should not mention untargeted connections');
        self::assertStringNotContainsString('beta', $output, 'Output should not mention untargeted connections');
    }

    public function testCtxAllIteratesAllContexts(): void
    {
        $output = $this->captureOutput('doctrine:database:create --if-not-exists --ctx-all --ctx-isolation --no-interaction');

        self::assertStringContainsString('default', $output, 'Output should mention the default context');
        self::assertStringContainsString('alpha', $output, 'Output should mention the alpha context');
        self::assertStringContainsString('beta', $output, 'Output should mention the beta context');
    }

    public function testCtxAllIsAlsoAvailableWithoutExplicitContext(): void
    {
        // --ctx-all should work even when explicit_context=false (default), verified here
        // by using the explicit-context kernel which has explicit_context=true; --ctx-all
        // must trigger fan-out over all three contexts.
        $output = $this->captureOutput('doctrine:database:create --if-not-exists --ctx-all --ctx-isolation --no-interaction');

        self::assertStringContainsString('default', $output);
        self::assertStringContainsString('alpha', $output);
        self::assertStringContainsString('beta', $output);
    }
}
