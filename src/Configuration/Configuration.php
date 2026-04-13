<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Configuration;

use Doctrine\Migrations\DependencyFactory;

use function array_keys;

class Configuration
{
    /** @var array<string, bool> context name => isEntityManager */
    private array $contexts = [];

    /** @var array<string, DependencyFactory> */
    private array $dependencyFactories = [];

    public function registerContext(string $contextName, bool $isEntityManager): self
    {
        $this->contexts[$contextName] = $isEntityManager;

        return $this;
    }

    public function addDependencyFactory(string $contextName, DependencyFactory $dependencyFactory): self
    {
        $this->contexts[$contextName]            = $dependencyFactory->hasEntityManager();
        $this->dependencyFactories[$contextName] = $dependencyFactory;

        return $this;
    }

    /** @return array<string, DependencyFactory> */
    public function getDependencyFactories(): array
    {
        return $this->dependencyFactories;
    }

    /** @return string[] */
    public function getContextNames(): array
    {
        return array_keys($this->contexts);
    }

    public function isEntityManager(string $contextName): bool
    {
        return $this->contexts[$contextName] ?? false;
    }

    public function findDependencyFactory(string $contextName): DependencyFactory|null
    {
        return $this->dependencyFactories[$contextName] ?? null;
    }
}
