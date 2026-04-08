<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Command\Doctrine\Schema;

use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Kraz\DoctrineContextBundle\Command\Doctrine\DoctrineContextTrait;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateSchemaCommand extends Command
{
    use DoctrineContextTrait;

    public function __construct(
        private readonly \Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand $command,
        Configuration $configuration,
        EntityManagerProvider|null $entityManagerProvider = null,
    ) {
        $this->configuration         = $configuration;
        $this->entityManagerProvider = $entityManagerProvider;

        parent::__construct($this->command->getName(), $this->command->getCode());
    }

    #[Override]
    protected function configure(): void
    {
        $this->configureAs($this->command);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runAs($this->command, $input, $output);
    }
}
