<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Strategy;

use Kraz\DoctrineContextBundle\Command\Doctrine\ContextRunner;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function trim;

final class ConnectionStrategy implements CommandExecutionStrategy
{
    #[Override]
    public function supports(Command $command): bool
    {
        return $command->getNativeDefinition()->hasOption('connection');
    }

    #[Override]
    public function configure(Command $wrapper, Command $innerCommand): void
    {
        $wrapper->addOption('conn', null, InputOption::VALUE_OPTIONAL, 'The name of the connection to use (alias for --connection).');
        $wrapper->addOption('connections', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the connections to use (multi-value alias for --connection).');
        $wrapper->addOption('conns', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the connections to use (multi-value alias for --conn).');
    }

    #[Override]
    public function execute(Command $wrapper, Command $innerCommand, ContextRunner $contextRunner, Configuration $configuration, InputInterface $input, OutputInterface $output): int
    {
        $connectionOption  = trim((string) $input->getOption('connection'));
        $connOption        = trim((string) $input->getOption('conn'));
        $connectionsOption = $contextRunner->resolveArrayOption($input, 'connections');
        $connsOption       = $contextRunner->resolveArrayOption($input, 'conns');

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

        if ($configuration->isExplicitContext() && count($targetConnectionNames) === 0 && ! $input->getOption('ctx-all')) {
            throw new InvalidArgumentException('Explicit context is required. Specify a context via --connection, --connections, --conn, --conns, or use --ctx-all to run over all contexts.');
        }

        $list = $contextRunner->filterDoctrineContexts(null, [], $targetConnectionNames);

        return $contextRunner->walkDoctrineContexts(static function (InputInterface $input, OutputInterface $output, string $contextName) use ($wrapper, $innerCommand, $contextRunner) {
            $innerCommand->setDefinition($wrapper->getNativeDefinition());
            $innerCommand->setApplication($wrapper->getApplication());
            $newInput = $contextRunner->createNewInput($innerCommand, $input, ['--connection' => $contextName]);

            return $innerCommand->run($newInput, $output);
        }, $list, $input, $output);
    }
}
