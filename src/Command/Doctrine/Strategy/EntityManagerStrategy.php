<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Strategy;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\Tools\Console\Command\AbstractEntityManagerCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\ContextRunner;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function is_string;
use function trim;

final class EntityManagerStrategy implements CommandExecutionStrategy
{
    #[Override]
    public function supports(Command $command): bool
    {
        return $command instanceof AbstractEntityManagerCommand;
    }

    #[Override]
    public function configure(Command $wrapper, Command $innerCommand): void
    {
        $wrapper->addOption('ems', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The names of the entity managers to use (multi-value alias for --em).');
    }

    #[Override]
    public function execute(Command $wrapper, Command $innerCommand, ContextRunner $contextRunner, Configuration $configuration, InputInterface $input, OutputInterface $output): int
    {
        $emOption  = trim((string) $input->getOption('em'));
        $emsOption = $contextRunner->resolveArrayOption($input, 'ems');

        if ($emOption !== '' && count($emsOption) > 0) {
            throw new InvalidArgumentException('You can specify only one of the --em and --ems options.');
        }

        $targetEntityManagers = $emOption !== '' ? [$emOption] : $emsOption;

        if ($configuration->isExplicitContext() && count($targetEntityManagers) === 0 && ! $input->getOption('ctx-all')) {
            throw new InvalidArgumentException('Explicit context is required. Specify a context via --em, --ems, or use --ctx-all to run over all contexts.');
        }

        $list = $contextRunner->filterDoctrineContexts(static fn (DependencyFactory|string $ctx): bool => is_string($ctx) ? $configuration->isEntityManager($ctx) : $ctx->hasEntityManager(), $targetEntityManagers);

        return $contextRunner->walkDoctrineContexts(static function (InputInterface $input, OutputInterface $output, string $em) use ($wrapper, $innerCommand, $contextRunner) {
            $innerCommand->setDefinition($wrapper->getNativeDefinition());
            $innerCommand->setApplication($wrapper->getApplication());
            $newInput = $contextRunner->createNewInput($innerCommand, $input, ['--em' => $em]);

            return $innerCommand->run($newInput, $output);
        }, $list, $input, $output);
    }
}
