<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Unit;

use Kraz\DoctrineContextBundle\Command\Doctrine\ContextRunner;
use Kraz\DoctrineContextBundle\Command\Doctrine\Strategy\CommandExecutionStrategy;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use ValueError;

class ContextRunnerTest extends TestCase
{
    public function testRunAsThrowsForUnsupportedCommand(): void
    {
        $runner  = new ContextRunner(new Configuration());
        $command = new Command('test:unsupported');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported CLI command');

        $runner->run($command, $command, new ArrayInput([]), new NullOutput());
    }

    public function testRunAsThrowsWhenNoStrategiesRegistered(): void
    {
        $runner  = new ContextRunner(new Configuration());
        $command = new Command('test:any');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported CLI command');

        $runner->run($command, $command, new ArrayInput([]), new NullOutput());
    }

    public function testRunAsDelegatesToFirstMatchingStrategy(): void
    {
        $runner = new ContextRunner(new Configuration());

        $nonMatching = $this->createMock(CommandExecutionStrategy::class);
        $nonMatching->method('supports')->willReturn(false);
        $nonMatching->expects($this->never())->method('execute');

        $matching = $this->createMock(CommandExecutionStrategy::class);
        $matching->method('supports')->willReturn(true);
        $matching->expects($this->once())->method('execute')->willReturn(Command::SUCCESS);

        $runner->addStrategy($nonMatching);
        $runner->addStrategy($matching);

        $command = new Command('test:matched');
        $result  = $runner->run($command, $command, new ArrayInput([]), new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testFirstMatchingStrategyWinsWhenMultipleMatch(): void
    {
        $runner = new ContextRunner(new Configuration());

        $first = $this->createMock(CommandExecutionStrategy::class);
        $first->method('supports')->willReturn(true);
        $first->expects($this->once())->method('execute')->willReturn(Command::SUCCESS);

        $second = $this->createMock(CommandExecutionStrategy::class);
        $second->method('supports')->willReturn(true);
        $second->expects($this->never())->method('execute');

        $runner->addStrategy($first);
        $runner->addStrategy($second);

        $command = new Command('test:both-match');
        $result  = $runner->run($command, $command, new ArrayInput([]), new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testConfigureAsDelegatesToMatchingStrategy(): void
    {
        $runner = new ContextRunner(new Configuration());

        $nonMatching = $this->createMock(CommandExecutionStrategy::class);
        $nonMatching->method('supports')->willReturn(false);
        $nonMatching->expects($this->never())->method('configure');

        $matching = $this->createMock(CommandExecutionStrategy::class);
        $matching->method('supports')->willReturn(true);
        $matching->expects($this->once())->method('configure');

        $runner->addStrategy($nonMatching);
        $runner->addStrategy($matching);

        $wrapper = new Command('test:wrapper');
        $inner   = new Command('test:inner');
        $inner->setDescription('Test description');

        $runner->configure($wrapper, $inner);
    }

    public function testConfigureAsAddsContextOptionsRegardlessOfStrategy(): void
    {
        $runner = new ContextRunner(new Configuration());

        $strategy = $this->createStub(CommandExecutionStrategy::class);
        $strategy->method('supports')->willReturn(true);
        $runner->addStrategy($strategy);

        $wrapper = new Command('test:wrapper');
        $inner   = new Command('test:inner');
        $inner->setDescription('Test description');

        $runner->configure($wrapper, $inner);

        self::assertTrue($wrapper->getDefinition()->hasOption('ctx-isolation'));
        self::assertTrue($wrapper->getDefinition()->hasOption('ctx-all'));
        self::assertTrue($wrapper->getDefinition()->hasOption('ctx-output-style'));
    }

    public function testWalkDoctrineContextsSectionStylePrintsSectionHeaders(): void
    {
        $configuration = new Configuration();
        $configuration->registerContext('alpha', false);
        $configuration->registerContext('beta', true);
        $runner = new ContextRunner($configuration);

        $output = new BufferedOutput();
        $input  = $this->createWalkInput('section');

        $runner->walkDoctrineContexts(
            static fn () => Command::SUCCESS,
            ['alpha' => null, 'beta' => null],
            $input,
            $output,
        );

        $result = $output->fetch();
        self::assertStringContainsString('Connection: alpha', $result);
        self::assertStringContainsString('Entity Manager: beta', $result);
    }

    public function testWalkDoctrineContextsLineStylePrintsInlinePrefix(): void
    {
        $configuration = new Configuration();
        $configuration->registerContext('alpha', false);
        $configuration->registerContext('beta', true);
        $runner = new ContextRunner($configuration);

        $output = new BufferedOutput();
        $input  = $this->createWalkInput('line');

        $runner->walkDoctrineContexts(
            static function (mixed $input, mixed $output) {
                $output->write('some-output');

                return Command::SUCCESS;
            },
            ['alpha' => null, 'beta' => null],
            $input,
            $output,
        );

        $result = $output->fetch();
        self::assertStringContainsString('(c) alpha: some-output', $result);
        self::assertStringContainsString('(e) beta: some-output', $result);
    }

    public function testWalkDoctrineContextsNoneStylePrintsNoContextName(): void
    {
        $configuration = new Configuration();
        $configuration->registerContext('alpha', false);
        $configuration->registerContext('beta', true);
        $runner = new ContextRunner($configuration);

        $output = new BufferedOutput();
        $input  = $this->createWalkInput('none');

        $runner->walkDoctrineContexts(
            static fn () => Command::SUCCESS,
            ['alpha' => null, 'beta' => null],
            $input,
            $output,
        );

        $result = $output->fetch();
        self::assertStringNotContainsString('alpha', $result);
        self::assertStringNotContainsString('beta', $result);
    }

    public function testWalkDoctrineContextsThrowsForInvalidOutputStyle(): void
    {
        $runner = new ContextRunner(new Configuration());

        $output = new BufferedOutput();
        $input  = $this->createWalkInput('invalid');

        $this->expectException(ValueError::class);

        $runner->walkDoctrineContexts(
            static fn () => Command::SUCCESS,
            ['alpha' => null],
            $input,
            $output,
        );
    }

    private function createWalkInput(string $outputStyle): ArrayInput
    {
        $definition = new InputDefinition([
            new InputOption('ctx-isolation', null, InputOption::VALUE_NONE),
            new InputOption('ctx-output-style', null, InputOption::VALUE_REQUIRED, '', 'section'),
        ]);

        $input = new ArrayInput(['--ctx-output-style' => $outputStyle], $definition);
        $input->setInteractive(false);

        return $input;
    }
}
