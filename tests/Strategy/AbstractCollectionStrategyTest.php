<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Strategy;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Laminas\Hydrator\Strategy\AbstractCollectionStrategy;
use Doctrine\Persistence\Mapping\ClassMetadata;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

class AbstractCollectionStrategyTest extends TestCase
{
    public function testDefaultInflector(): void
    {
        $strategy = $this->getMockForAbstractClass(AbstractCollectionStrategy::class);

        $reflection = new ReflectionMethod(AbstractCollectionStrategy::class, 'getInflector');
        $reflection->setAccessible(true);

        $this->assertInstanceOf(Inflector::class, $reflection->invoke($strategy));
    }

    public function testCustomInflector(): void
    {
        $inflector = InflectorFactory::create()->build();
        $strategy  = $this->getMockForAbstractClass(AbstractCollectionStrategy::class, [$inflector]);

        $reflection = new ReflectionMethod(AbstractCollectionStrategy::class, 'getInflector');
        $reflection->setAccessible(true);

        $this->assertInstanceOf(Inflector::class, $reflection->invoke($strategy));
        $this->assertSame($inflector, $reflection->invoke($strategy));
    }

    public function testUninitializedCollectionNameThrowsException(): void
    {
        $strategy = $this->getMockForAbstractClass(AbstractCollectionStrategy::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Collection name has not been set.');

        $strategy->getCollectionName();
    }

    public function testUninitializedClassMetadataThrowsException(): void
    {
        $strategy = $this->getMockForAbstractClass(AbstractCollectionStrategy::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class metadata has not been set.');

        $strategy->getClassMetadata();
    }

    public function testUninitializedObjectThrowsException(): void
    {
        $strategy = $this->getMockForAbstractClass(AbstractCollectionStrategy::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Object has not been set.');

        $strategy->getObject();
    }

    public function testSetAndGetCollectionName(): void
    {
        $strategy       = $this->getMockForAbstractClass(AbstractCollectionStrategy::class);
        $collectionName = 'sampleCollection';

        $strategy->setCollectionName($collectionName);
        $this->assertSame($collectionName, $strategy->getCollectionName());
    }

    public function testSetAndGetClassMetadata(): void
    {
        $strategy      = $this->getMockForAbstractClass(AbstractCollectionStrategy::class);
        $classMetadata = $this->createStub(ClassMetadata::class);

        $strategy->setClassMetadata($classMetadata);
        $this->assertSame($classMetadata, $strategy->getClassMetadata());
    }

    public function testSetAndGetObject(): void
    {
        $strategy = $this->getMockForAbstractClass(AbstractCollectionStrategy::class);
        $object   = new stdClass();

        $strategy->setObject($object);
        $this->assertSame($object, $strategy->getObject());
    }
}
