<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Configuration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ConnectionLoader;
use Doctrine\Migrations\Configuration\Exception\InvalidLoader;
use Override;

class ExistingNamedConnection implements ConnectionLoader
{
    public function __construct(
        private readonly string $name,
        private readonly Connection $connection,
    ) {
    }

    #[Override]
    public function getConnection(string|null $name = null): Connection
    {
        if ($name !== null && $this->name !== $name) {
            throw InvalidLoader::noMultipleConnections($this);
        }

        return $this->connection;
    }
}
