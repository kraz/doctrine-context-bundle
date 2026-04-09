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
}
