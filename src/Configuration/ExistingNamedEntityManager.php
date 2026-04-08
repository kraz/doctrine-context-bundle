<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Configuration;

use Doctrine\Migrations\Configuration\EntityManager\EntityManagerLoader;
use Doctrine\Migrations\Configuration\Exception\InvalidLoader;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final class ExistingNamedEntityManager implements EntityManagerLoader
{
    public function __construct(
        private readonly string $name,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function getEntityManager(string|null $name = null): EntityManagerInterface
    {
        if ($name !== null && $this->name !== $name) {
            throw InvalidLoader::noMultipleEntityManagers($this);
        }

        return $this->entityManager;
    }
}
