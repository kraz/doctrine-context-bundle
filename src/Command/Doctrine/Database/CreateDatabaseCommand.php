<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Database;

use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Kraz\DoctrineContextBundle\Command\Doctrine\ContextRunner;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDatabaseCommand extends Command
{
    public function __construct(
        private readonly CreateDatabaseDoctrineCommand $command,
        private readonly ContextRunner $contextRunner,
    ) {
        parent::__construct($this->command->getName(), $this->command->getCode());
    }

    #[Override]
    protected function configure(): void
    {
        $this->contextRunner->configure($this, $this->command);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->contextRunner->run($this, $this->command, $input, $output);
    }
}
