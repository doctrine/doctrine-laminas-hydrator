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
    private function createStrategy(Inflector|null $inflector = null): AbstractCollectionStrategy
    {
        return new class ($inflector) extends AbstractCollectionStrategy {
            /** @param array<array-key, mixed> $data */
            public function hydrate(mixed $value, array|null $data = null): mixed
            {
                return $value;
            }
        };
    }

    public function testDefaultInflector(): void
    {
        $strategy = $this->createStrategy();

        $reflection = new ReflectionMethod(AbstractCollectionStrategy::class, 'getInflector');
        $reflection->setAccessible(true);

        $this->assertInstanceOf(Inflector::class, $reflection->invoke($strategy));
    }

    public function testCustomInflector(): void
    {
        $inflector = InflectorFactory::create()->build();
        $strategy  = $this->createStrategy($inflector);

        $reflection = new ReflectionMethod(AbstractCollectionStrategy::class, 'getInflector');
        $reflection->setAccessible(true);

        $this->assertInstanceOf(Inflector::class, $reflection->invoke($strategy));
        $this->assertSame($inflector, $reflection->invoke($strategy));
    }

    public function testUninitializedCollectionNameThrowsException(): void
    {
        $strategy = $this->createStrategy();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Collection name has not been set.');

        $strategy->getCollectionName();
    }

    public function testUninitializedClassMetadataThrowsException(): void
    {
        $strategy = $this->createStrategy();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class metadata has not been set.');

        $strategy->getClassMetadata();
    }

    public function testUninitializedObjectThrowsException(): void
    {
        $strategy = $this->createStrategy();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Object has not been set.');

        $strategy->getObject();
    }

    public function testSetAndGetCollectionName(): void
    {
        $strategy       = $this->createStrategy();
        $collectionName = 'sampleCollection';

        $strategy->setCollectionName($collectionName);
        $this->assertSame($collectionName, $strategy->getCollectionName());
    }

    public function testSetAndGetClassMetadata(): void
    {
        $strategy      = $this->createStrategy();
        $classMetadata = $this->createStub(ClassMetadata::class);

        $strategy->setClassMetadata($classMetadata);
        $this->assertSame($classMetadata, $strategy->getClassMetadata());
    }

    public function testSetAndGetObject(): void
    {
        $strategy = $this->createStrategy();
        $object   = new stdClass();

        $strategy->setObject($object);
        $this->assertSame($object, $strategy->getObject());
    }
}
