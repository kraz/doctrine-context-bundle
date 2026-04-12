<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Database;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\DoctrineContextTrait;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDatabaseCommand extends Command
{
    use DoctrineContextTrait;

    public function __construct(
        private readonly CreateDatabaseDoctrineCommand $command,
        Configuration $configuration,
    ) {
        $this->configuration = $configuration;

        parent::__construct($this->command->getName(), $this->command->getCode());
    }

    #[Override]
    protected function configure(): void
    {
        $this->configureAs($this->command);
        $this->addOption('conn', null, InputOption::VALUE_OPTIONAL, 'The name of the connection to use (alias for --connection).');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runAs($this->command, $input, $output);
    }
}
