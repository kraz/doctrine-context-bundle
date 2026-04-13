<?php

declare(strict_types=1);

namespace Kraz\DoctrineContextBundle\Tests\Unit;

use Doctrine\Migrations\DependencyFactory;
use Kraz\DoctrineContextBundle\Configuration\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testAddAndRetrieveDependencyFactory(): void
    {
        $configuration = new Configuration();
        $factory       = $this->createStub(DependencyFactory::class);

        $configuration->addDependencyFactory('alpha', $factory);

        self::assertSame($factory, $configuration->findDependencyFactory('alpha'));
    }

    public function testGetContextNames(): void
    {
        $configuration = new Configuration();
        $configuration->addDependencyFactory('alpha', $this->createStub(DependencyFactory::class));
        $configuration->addDependencyFactory('beta', $this->createStub(DependencyFactory::class));

        self::assertSame(['alpha', 'beta'], $configuration->getContextNames());
    }

    public function testGetDependencyFactories(): void
    {
        $configuration = new Configuration();
        $factoryAlpha  = $this->createStub(DependencyFactory::class);
        $factoryBeta   = $this->createStub(DependencyFactory::class);

        $configuration->addDependencyFactory('alpha', $factoryAlpha);
        $configuration->addDependencyFactory('beta', $factoryBeta);

        self::assertSame(['alpha' => $factoryAlpha, 'beta' => $factoryBeta], $configuration->getDependencyFactories());
    }

    public function testFindNonexistentReturnsNull(): void
    {
        $configuration = new Configuration();

        self::assertNull($configuration->findDependencyFactory('nonexistent'));
    }

    public function testRegisterContextTracksNameWithoutDependencyFactory(): void
    {
        $configuration = new Configuration();
        $configuration->registerContext('alpha', false);
        $configuration->registerContext('beta', true);

        self::assertSame(['alpha', 'beta'], $configuration->getContextNames());
        self::assertEmpty($configuration->getDependencyFactories());
    }

    public function testIsEntityManagerReturnsTrueForEntityManagerContext(): void
    {
        $configuration = new Configuration();
        $configuration->registerContext('alpha', true);
        $configuration->registerContext('beta', false);

        self::assertTrue($configuration->isEntityManager('alpha'));
        self::assertFalse($configuration->isEntityManager('beta'));
    }

    public function testIsEntityManagerReturnsFalseForUnknownContext(): void
    {
        $configuration = new Configuration();

        self::assertFalse($configuration->isEntityManager('unknown'));
    }

    public function testAddDependencyFactoryAlsoRegistersContext(): void
    {
        $configuration = new Configuration();
        $factory       = $this->createStub(DependencyFactory::class);

        $configuration->addDependencyFactory('alpha', $factory);

        self::assertSame(['alpha'], $configuration->getContextNames());
    }

    public function testGetContextNamesIncludesContextsRegisteredWithoutDependencyFactory(): void
    {
        $configuration = new Configuration();
        $factory       = $this->createStub(DependencyFactory::class);

        $configuration->registerContext('alpha', false);
        $configuration->addDependencyFactory('beta', $factory);

        self::assertSame(['alpha', 'beta'], $configuration->getContextNames());
        self::assertSame(['beta' => $factory], $configuration->getDependencyFactories());
    }
}
