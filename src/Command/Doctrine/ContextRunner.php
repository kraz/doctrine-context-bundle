<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine;

use Doctrine\Migrations\DependencyFactory;
use Kraz\DoctrineContextBundle\Command\Doctrine\Strategy\CommandExecutionStrategy;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_key_first;
use function array_map;
use function array_replace;
use function array_shift;
use function array_unique;
use function array_values;
use function call_user_func;
use function count;
use function explode;
use function implode;
use function ksort;
use function method_exists;
use function sprintf;
use function trim;

final class ContextRunner
{
    /** @var list<CommandExecutionStrategy> */
    private array $strategies = [];

    public function __construct(private readonly Configuration $configuration)
    {
    }

    public function addStrategy(CommandExecutionStrategy $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function configure(Command $wrapper, Command $innerCommand): void
    {
        if (method_exists($wrapper, 'getCode') && $wrapper->getCode() !== null) {
            throw new InvalidArgumentException(sprintf('The CLI command "%s" is not expected to use custom execution code', $wrapper::class));
        }

        if (method_exists($innerCommand, 'getCode') && $innerCommand->getCode() !== null) {
            throw new InvalidArgumentException(sprintf('The CLI command "%s" is not expected to use custom execution code', $innerCommand::class));
        }

        $name = $wrapper->getName() ?? $innerCommand->getName();
        if ($name !== null) {
            $wrapper->setName($name);
        }

        $wrapper->setDescription(($wrapper->getDescription() ?: $innerCommand->getDescription()) . ' [Doctrine Context]');
        $wrapper->setAliases($wrapper->getAliases() ?: $innerCommand->getAliases());
        $wrapper->setHelp($wrapper->getHelp() ?: $innerCommand->getHelp());
        $nativeDefinition = $innerCommand->getNativeDefinition();
        $wrapper->setDefinition($nativeDefinition);
        $wrapper->addOption('ctx-isolation', null, InputOption::VALUE_NONE, 'Continue with the next context, if the current one fails.');
        $wrapper->addOption('ctx-all', null, InputOption::VALUE_NONE, 'Run the command over all registered contexts (when explicit_context: true).');

        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($innerCommand)) {
                $strategy->configure($wrapper, $innerCommand);
                break;
            }
        }
    }

    public function run(Command $wrapper, Command $innerCommand, InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($innerCommand)) {
                return $strategy->execute($wrapper, $innerCommand, $this, $this->configuration, $input, $output);
            }
        }

        throw new InvalidArgumentException(sprintf('Unsupported CLI command "%s"', $innerCommand::class));
    }

    /** @param array<string, DependencyFactory|null> $list */
    public function walkDoctrineContexts(callable $callback, array $list, InputInterface $input, OutputInterface $output): int
    {
        $result           = Command::SUCCESS;
        $ui               = new SymfonyStyle($input, $output)->getErrorStyle();
        $contextIsolation = $input->getOption('ctx-isolation');
        $total            = count($list);
        while (count($list) > 0) {
            $contextName       = array_key_first($list);
            $dependencyFactory = array_shift($list);
            if ($total > 1) {
                $ui->section(sprintf('%s: %s', $this->configuration->isEntityManager($contextName) ? 'Entity Manager' : 'Connection', $contextName));
            }

            try {
                $cmdResult = $callback($input, $output, $contextName, $dependencyFactory);
            } catch (Throwable $e) {
                $ui->error($e->getMessage());
                $cmdResult = Command::FAILURE;
            }

            if ($total > 1) {
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

    /**
     * @param (callable(DependencyFactory|string): bool)|null $filter
     * @param string[]                                        $targetEntityManagers
     * @param string[]                                        $targetConnectionNames
     *
     * @return array<string, DependencyFactory|null>
     */
    public function filterDoctrineContexts(callable|null $filter = null, array $targetEntityManagers = [], array $targetConnectionNames = []): array
    {
        $targetEntityManagers  = array_values(array_filter(array_map('trim', $targetEntityManagers)));
        $targetConnectionNames = array_values(array_filter(array_map('trim', $targetConnectionNames)));

        if (count($targetEntityManagers) > 0 && count($targetConnectionNames) > 0) {
            throw new InvalidArgumentException('You can specify only one of the --em/--ems and --conn/--conns options.');
        }

        $targetNames         = count($targetEntityManagers) > 0 ? $targetEntityManagers : $targetConnectionNames;
        $isEntityManagerMode = count($targetEntityManagers) > 0;

        // Build the full context pool (dependency factories + context-only entries)
        $allContexts = $this->configuration->getDependencyFactories();
        foreach ($this->configuration->getContextNames() as $name) {
            if (! isset($allContexts[$name])) {
                $allContexts[$name] = null;
            }
        }

        // Select the requested subset or take all
        if (count($targetNames) > 0) {
            $list = [];
            foreach ($targetNames as $name) {
                if (array_key_exists($name, $allContexts)) {
                    $list[$name] = $allContexts[$name];
                }
            }
        } else {
            $list = $allContexts;
        }

        // Apply callable filter
        if ($filter !== null) {
            $filteredList = [];
            foreach ($list as $contextName => $dependencyFactory) {
                if (! call_user_func($filter, $dependencyFactory ?? $contextName)) {
                    continue;
                }

                $filteredList[$contextName] = $dependencyFactory;
            }

            $list = $filteredList;
        }

        // Throw for invalid/not-found targets
        if (count($list) === 0 && count($targetNames) > 0) {
            if ($isEntityManagerMode) {
                throw new InvalidArgumentException(count($targetNames) === 1
                    ? sprintf('Unknown doctrine entity manager "%s" or it\'s not registered as doctrine context.', $targetNames[0])
                    : sprintf('Unknown doctrine entity managers "%s" or they are not registered as doctrine contexts.', implode('", "', $targetNames)));
            }

            throw new InvalidArgumentException(count($targetNames) === 1
                ? sprintf('Unknown doctrine connection "%s" or it\'s not registered as doctrine context.', $targetNames[0])
                : sprintf('Unknown doctrine connections "%s" or they are not registered as doctrine contexts.', implode('", "', $targetNames)));
        }

        ksort($list);

        return $list;
    }

    /** @return string[] */
    public function resolveArrayOption(InputInterface $input, string $name): array
    {
        if (! $input->hasOption($name)) {
            return [];
        }

        $values = [];
        foreach ((array) $input->getOption($name) as $value) {
            foreach (explode(',', $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $values[] = $part;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /** @param array<string, mixed> $override */
    public function createNewInput(Command $command, InputInterface $input, array $override): InputInterface
    {
        $definition = $command->getNativeDefinition();
        $parameters = [];
        foreach ($definition->getArguments() as $argument) {
            if ($input->hasArgument($argument->getName())) {
                $parameters[$argument->getName()] = $input->getArgument($argument->getName());
            }
        }

        foreach ($definition->getOptions() as $option) {
            if ($input->hasParameterOption('--' . $option->getName())) {
                $parameters['--' . $option->getName()] = $input->getOption($option->getName());
            }
        }

        $parameters = array_replace($parameters, $override);
        $newInput   = new ArrayInput($parameters);
        $newInput->setInteractive($input->isInteractive());

        return $newInput;
    }
}
