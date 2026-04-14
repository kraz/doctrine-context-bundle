<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Strategy;

use Kraz\DoctrineContextBundle\Command\Doctrine\ContextRunner;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface CommandExecutionStrategy
{
    public function supports(Command $command): bool;

    public function configure(Command $wrapper, Command $innerCommand): void;

    public function execute(Command $wrapper, Command $innerCommand, ContextRunner $contextRunner, Configuration $configuration, InputInterface $input, OutputInterface $output): int;
}
