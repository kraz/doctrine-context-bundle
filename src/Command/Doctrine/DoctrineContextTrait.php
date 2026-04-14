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
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_key_first;
use function array_map;
use function array_replace;
use function array_shift;
use function array_unique;
use function array_values;
use function assert;
use function call_user_func;
use function count;
use function explode;
use function implode;
use function is_string;
use function ksort;
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
        $nativeDefinition = $command->getNativeDefinition();
        $this->setDefinition($nativeDefinition);
        $this->addOption('ctx-isolation', null, InputOption::VALUE_NONE, 'Continue with the next context, if the current one fails.');
        $this->addOption('ctx-all', null, InputOption::VALUE_NONE, 'Run the command over all registered contexts (when explicit_context: true).');

        if ($nativeDefinition->hasOption('em')) {
            $this->addOption('ems', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the entity managers to use (multi-value alias for --em).');
        }

        if ($nativeDefinition->hasOption('conn')) {
            $this->addOption('conns', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the connections to use (multi-value alias for --conn).');
        }

        if ($nativeDefinition->hasOption('connection')) {
            $this->addOption('connections', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the connections to use (multi-value alias for --connection).');
            $this->addOption('conns', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the connections to use (multi-value alias for --conn).');
        }
    }

    private function runAs(Command $command, InputInterface $input, OutputInterface $output): int
    {
        assert($this instanceof Command);

        $ctxAll = (bool) $input->getOption('ctx-all');

        if ($command instanceof AbstractEntityManagerCommand) {
            $emOption  = trim((string) $input->getOption('em'));
            $emsOption = $this->resolveArrayOption($input, 'ems');

            if ($emOption !== '' && count($emsOption) > 0) {
                throw new InvalidArgumentException('You can specify only one of the --em and --ems options.');
            }

            $targetEntityManagers = $emOption !== '' ? [$emOption] : $emsOption;

            if ($this->configuration->isExplicitContext() && count($targetEntityManagers) === 0 && ! $ctxAll) {
                throw new InvalidArgumentException('Explicit context is required. Specify a context via --em, --ems, or use --ctx-all to run over all contexts.');
            }

            $list = $this->filterDoctrineContexts(fn (DependencyFactory|string $ctx): bool => is_string($ctx) ? $this->configuration->isEntityManager($ctx) : $ctx->hasEntityManager(), $targetEntityManagers);

            return $this->walkDoctrineContexts(function (InputInterface $input, OutputInterface $output, string $em) use ($command) {
                $command->setDefinition($this->getNativeDefinition());
                $command->setApplication($this->getApplication());
                $newInput = $this->createNewInput($command, $input, ['--em' => $em]);

                return $command->run($newInput, $output);
            }, $list, $input, $output);
        }

        if ($command instanceof AbstractDoctrineMigrationCommand) {
            $emOption    = trim((string) $input->getOption('em'));
            $connOption  = trim((string) $input->getOption('conn'));
            $emsOption   = $this->resolveArrayOption($input, 'ems');
            $connsOption = $this->resolveArrayOption($input, 'conns');

            if ($emOption !== '' && count($emsOption) > 0) {
                throw new InvalidArgumentException('You can specify only one of the --em and --ems options.');
            }

            if ($connOption !== '' && count($connsOption) > 0) {
                throw new InvalidArgumentException('You can specify only one of the --conn and --conns options.');
            }

            $targetEntityManagers  = $emOption !== '' ? [$emOption] : $emsOption;
            $targetConnectionNames = $connOption !== '' ? [$connOption] : $connsOption;

            if ($this->configuration->isExplicitContext() && count($targetEntityManagers) === 0 && count($targetConnectionNames) === 0 && ! $ctxAll) {
                throw new InvalidArgumentException('Explicit context is required. Specify a context via --em, --ems, --conn, --conns, or use --ctx-all to run over all contexts.');
            }

            $list = $this->filterDoctrineContexts(null, $targetEntityManagers, $targetConnectionNames);

            return $this->walkDoctrineContexts(function (InputInterface $input, OutputInterface $output, string $contextName, DependencyFactory $dependencyFactory) use ($command) {
                $command = new ReflectionClass($command)->newInstance($dependencyFactory);
                $command->setDefinition($this->getNativeDefinition());
                $command->setApplication($this->getApplication());

                return $command->run($input, $output);
            }, $list, $input, $output);
        }

        if ($command->getNativeDefinition()->hasOption('connection')) {
            $connectionOption  = trim((string) $input->getOption('connection'));
            $connOption        = trim((string) $input->getOption('conn'));
            $connectionsOption = $this->resolveArrayOption($input, 'connections');
            $connsOption       = $this->resolveArrayOption($input, 'conns');

            if ($connectionOption !== '' && $connOption !== '') {
                throw new InvalidArgumentException('You can specify only one of the --connection and --conn options.');
            }

            if (count($connectionsOption) > 0 && count($connsOption) > 0) {
                throw new InvalidArgumentException('You can specify only one of the --connections and --conns options.');
            }

            $singleTarget = $connectionOption ?: ($connOption ?: null);
            $arrayTargets = count($connectionsOption) > 0 ? $connectionsOption : $connsOption;

            if ($singleTarget !== null && count($arrayTargets) > 0) {
                throw new InvalidArgumentException('You can specify only one of the --connection/--conn and --connections/--conns options.');
            }

            $targetConnectionNames = $singleTarget !== null ? [$singleTarget] : $arrayTargets;

            if ($this->configuration->isExplicitContext() && count($targetConnectionNames) === 0 && ! $ctxAll) {
                throw new InvalidArgumentException('Explicit context is required. Specify a context via --connection, --connections, --conn, --conns, or use --ctx-all to run over all contexts.');
            }

            $list = $this->filterDoctrineContexts(null, [], $targetConnectionNames);

            return $this->walkDoctrineContexts(function (InputInterface $input, OutputInterface $output, string $contextName) use ($command) {
                $command->setDefinition($this->getNativeDefinition());
                $command->setApplication($this->getApplication());
                $newInput = $this->createNewInput($command, $input, ['--connection' => $contextName]);

                return $command->run($newInput, $output);
            }, $list, $input, $output);
        }

        throw new InvalidArgumentException(sprintf('Unsupported CLI command "%s"', $command::class));
    }

    /** @param array<string, DependencyFactory|null> $list */
    private function walkDoctrineContexts(callable $callback, array $list, InputInterface $input, OutputInterface $output): int
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
     * @param callable(DependencyFactory|string): bool|null $filter
     * @param string[]                                      $targetEntityManagers
     * @param string[]                                      $targetConnectionNames
     *
     * @return array<string, DependencyFactory|null>
     */
    private function filterDoctrineContexts(callable|null $filter = null, array $targetEntityManagers = [], array $targetConnectionNames = []): array
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
    private function resolveArrayOption(InputInterface $input, string $name): array
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
