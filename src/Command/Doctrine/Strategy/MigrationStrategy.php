<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Strategy;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand as AbstractDoctrineMigrationCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\ContextRunner;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use Override;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function trim;

final class MigrationStrategy implements CommandExecutionStrategy
{
    #[Override]
    public function supports(Command $command): bool
    {
        return $command instanceof AbstractDoctrineMigrationCommand;
    }

    #[Override]
    public function configure(Command $wrapper, Command $innerCommand): void
    {
        $nativeDefinition = $innerCommand->getNativeDefinition();

        if ($nativeDefinition->hasOption('em')) {
            $wrapper->addOption('ems', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the entity managers to use (multi-value alias for --em).');
        }

        if ($nativeDefinition->hasOption('conn')) {
            $wrapper->addOption('conns', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the connections to use (multi-value alias for --conn).');
        }
    }

    #[Override]
    public function execute(Command $wrapper, Command $innerCommand, ContextRunner $contextRunner, Configuration $configuration, InputInterface $input, OutputInterface $output): int
    {
        $emOption    = trim((string) $input->getOption('em'));
        $connOption  = trim((string) $input->getOption('conn'));
        $emsOption   = $contextRunner->resolveArrayOption($input, 'ems');
        $connsOption = $contextRunner->resolveArrayOption($input, 'conns');

        if ($emOption !== '' && count($emsOption) > 0) {
            throw new InvalidArgumentException('You can specify only one of the --em and --ems options.');
        }

        if ($connOption !== '' && count($connsOption) > 0) {
            throw new InvalidArgumentException('You can specify only one of the --conn and --conns options.');
        }

        $targetEntityManagers  = $emOption !== '' ? [$emOption] : $emsOption;
        $targetConnectionNames = $connOption !== '' ? [$connOption] : $connsOption;

        if ($configuration->isExplicitContext() && count($targetEntityManagers) === 0 && count($targetConnectionNames) === 0 && ! $input->getOption('ctx-all')) {
            throw new InvalidArgumentException('Explicit context is required. Specify a context via --em, --ems, --conn, --conns, or use --ctx-all to run over all contexts.');
        }

        $list = $contextRunner->filterDoctrineContexts(null, $targetEntityManagers, $targetConnectionNames);

        return $contextRunner->walkDoctrineContexts(static function (InputInterface $input, OutputInterface $output, string $contextName, DependencyFactory $dependencyFactory) use ($wrapper, $innerCommand) {
            $command = new ReflectionClass($innerCommand)->newInstance($dependencyFactory);
            $command->setDefinition($wrapper->getNativeDefinition());
            $command->setApplication($wrapper->getApplication());

            return $command->run($input, $output);
        }, $list, $input, $output);
    }
}
