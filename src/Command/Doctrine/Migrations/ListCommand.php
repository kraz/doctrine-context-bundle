<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Migrations;

use Kraz\DoctrineContextBundle\Command\Doctrine\ContextRunner;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{
    public function __construct(
        private readonly \Doctrine\Migrations\Tools\Console\Command\ListCommand $command,
        private readonly ContextRunner $contextRunner,
    ) {
        parent::__construct($this->command->getName());
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
