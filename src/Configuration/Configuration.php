<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Configuration;

use Doctrine\Migrations\DependencyFactory;

use function array_keys;

class Configuration
{
    /** @var array<string, DependencyFactory> */
    private array $dependencyFactories = [];

    public function addDependencyFactory(string $contextName, DependencyFactory $dependencyFactory): self
    {
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
        return array_keys($this->dependencyFactories);
    }

    public function findDependencyFactory(string $contextName): DependencyFactory|null
    {
        return $this->dependencyFactories[$contextName] ?? null;
    }
}
