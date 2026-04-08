<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand as AbstractDoctrineMigrationCommand;
use Doctrine\ORM\Tools\Console\Command\AbstractEntityManagerCommand;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_filter;
use function array_replace;
use function array_search;
use function array_shift;
use function asort;
use function assert;
use function count;
use function sprintf;
use function trim;

trait DoctrineContextTrait
{
    private Configuration $configuration;
    private EntityManagerProvider|null $entityManagerProvider = null;

    private function configureAs(Command $command): void
    {
        assert($this instanceof Command);

        $name = $this->getName() ?? $command->getName();
        if ($name !== null) {
            $this->setName($name);
        }

        $this->setDescription(($this->getDescription() ?: $command->getDescription()) . ' [Doctrine Context]');
        $this->setAliases($this->getAliases() ?: $command->getAliases());
        $this->setHelp($this->getHelp() ?: $command->getHelp());
        $this->setDefinition($command->getNativeDefinition());
        $this->addOption('ctx-isolation', null, InputOption::VALUE_NONE, 'Continue with the next context, if the current one fails.');
    }

    private function runAs(Command $command, InputInterface $input, OutputInterface $output): int
    {
        assert($this instanceof Command);

        if ($command instanceof AbstractEntityManagerCommand) {
            $list = $this->filterDoctrineContexts(static fn (DependencyFactory $dependencyFactory) => $dependencyFactory->hasEntityManager(), (string) $input->getOption('em'));

            return $this->walkDoctrineContexts(function (InputInterface $input, OutputInterface $output, string $em) use ($command) {
                $command->setDefinition($this->getNativeDefinition());
                $command->setApplication($this->getApplication());
                $newInput = $this->createNewInput($command, $input, ['--em' => $em]);

                return $command->run($newInput, $output);
            }, $list, $input, $output);
        }

        if ($command instanceof AbstractDoctrineMigrationCommand) {
            $list = $this->filterDoctrineContexts(null, (string) $input->getOption('em'), (string) $input->getOption('conn'));

            return $this->walkDoctrineContexts(function (InputInterface $input, OutputInterface $output, string $contextName, DependencyFactory $dependencyFactory) use ($command) {
                $command = new ReflectionClass($command)->newInstance($dependencyFactory);
                $command->setDefinition($this->getNativeDefinition());
                $command->setApplication($this->getApplication());

                return $command->run($input, $output);
            }, $list, $input, $output);
        }

        throw new InvalidArgumentException(sprintf('Unsupported CLI command "%s"', $command::class));
    }

    /** @param array<string, DependencyFactory> $list */
    private function walkDoctrineContexts(callable $callback, array $list, InputInterface $input, OutputInterface $output): int
    {
        $all              = $list;
        $result           = Command::SUCCESS;
        $ui               = new SymfonyStyle($input, $output)->getErrorStyle();
        $contextIsolation = $input->getOption('ctx-isolation');
        while ($dependencyFactory = array_shift($list)) {
            $contextName = array_search($dependencyFactory, $all);
            assert($contextName !== false);
            if (count($all) > 1) {
                $ui->section(sprintf('%s: %s', $dependencyFactory->hasEntityManager() ? 'Entity Manager' : 'Connection', $contextName));
            }

            $cmdResult = $callback($input, $output, $contextName, $dependencyFactory);
            if (count($all) > 1) {
                $ui->newLine();
            }

            if ($cmdResult !== Command::SUCCESS) {
                $result = $cmdResult;
                if ($input->isInteractive() && count($list) > 0 && $ui->confirm('Do you want to proceed with the rest of the doctrine contexts?')) {
                    continue;
                }

                if (! $contextIsolation) {
                    break;
                }
            }
        }

        return $result;
    }

    /** @return array<string, DependencyFactory> */
    private function filterDoctrineContexts(callable|null $filter = null, string|null $targetEntityManager = null, string|null $targetConnectionName = null): array
    {
        $targetEntityManager  = trim($targetEntityManager ?? '') ?: null;
        $targetConnectionName = trim($targetConnectionName ?? '') ?: null;
        if ($targetEntityManager !== null && $targetConnectionName !== null) {
            throw new InvalidArgumentException('You can specify only one of the --em and --conn options.');
        }

        $contextName = $targetEntityManager ?: $targetConnectionName;
        if ($contextName !== null) {
            $dependencyFactory = $this->configuration->findDependencyFactory($contextName);
            $list              = $dependencyFactory !== null ? [$contextName => $dependencyFactory] : [];
        } else {
            $list = $this->configuration->getDependencyFactories();
        }

        if ($filter !== null) {
            $list = array_filter($list, $filter);
        }

        if (count($list) === 0 && $targetEntityManager !== null) {
            throw new InvalidArgumentException(sprintf('Unknown doctrine entity manager "%s" or it\'s not registered as doctrine context.', $targetEntityManager));
        }

        if (count($list) === 0 && $targetConnectionName !== null) {
            throw new InvalidArgumentException(sprintf('Unknown doctrine connection "%s" or it\'s not registered as doctrine context.', $targetConnectionName));
        }

        asort($list);

        return $list;
    }

    /** @param array<string, mixed> $override */
    private function createNewInput(Command $command, InputInterface $input, array $override): InputInterface
    {
        $definition = $command->getNativeDefinition();
        $parameters = [];
        foreach ($definition->getArguments() as $argument) {
            if ($input->hasArgument($argument->getName())) {
                $parameters[$argument->getName()] = $input->getArgument($argument->getName());
            }
        }

        foreach ($definition->getOptions() as $option) {
            if ($input->hasParameterOption($option->getName())) {
                $parameters['--' . $option->getName()] = $input->getOption($option->getName());
            }
        }

        $parameters = array_replace($parameters, $override);
        $newInput   = new ArrayInput($parameters);
        $newInput->setInteractive($input->isInteractive());

        return $newInput;
    }
}
