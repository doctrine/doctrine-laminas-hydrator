<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use DoctrineTest\Laminas\Hydrator\Assets\ByValueDifferentiatorEntity;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithIsBoolean;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithUninitializedProperties;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleIsEntity;
use DoctrineTest\Laminas\Hydrator\Assets\SimplePrivateEntity;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

#[CoversClass(DoctrineObject::class)]
class SimpleFieldsTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testExtract(bool $byValue): void
    {
        $entity = new ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $hydrator = $this->createHydrator($this->createObjectManager([$this->createByValueDifferentiatorMetadata()]), $byValue);

        $this->assertSame(
            ['id' => 2, 'field' => $byValue ? 'From getter: foo' : 'foo'],
            $hydrator->extract($entity),
        );
    }

    #[DataProvider('byValueProvider')]
    public function testHydrate(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createObjectManager([$this->createByValueDifferentiatorMetadata()]), $byValue);

        $entity = $hydrator->hydrate(['field' => 'foo'], new ByValueDifferentiatorEntity());

        $this->assertInstanceOf(ByValueDifferentiatorEntity::class, $entity);
        $this->assertSame($byValue ? 'From setter: foo' : 'foo', $entity->getField(false));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateWithStringIdentifier(bool $byValue): void
    {
        $hydrator = $this->createHydrator(
            $this->createObjectManager([$this->createByValueDifferentiatorMetadata('string')]),
            $byValue,
        );

        $entity = $hydrator->hydrate(['id' => 'bar', 'field' => 'foo'], new ByValueDifferentiatorEntity());

        $this->assertSame('bar', $entity->getId());
        $this->assertSame($byValue ? 'From setter: foo' : 'foo', $entity->getField(false));
    }

    /** @return iterable<string, array{bool, int}> */
    public static function existingEntityProvider(): iterable
    {
        yield 'by value' => [true, 1];
        yield 'by value, zero identifier' => [true, 0];
        yield 'by reference' => [false, 1];
        yield 'by reference, zero identifier' => [false, 0];
    }

    /**
     * Zero identifier: https://github.com/doctrine/DoctrineModule/issues/456
     */
    #[DataProvider('existingEntityProvider')]
    public function testReuseExistingEntityIfDataContainsIdentifier(bool $byValue, int $id): void
    {
        $entityInDatabase = new ByValueDifferentiatorEntity();
        $entityInDatabase->setId($id);
        $entityInDatabase->setField('bar', false);

        $objectManager = $this->createObjectManagerMock([$this->createByValueDifferentiatorMetadata()]);
        $objectManager
            ->expects($this->once())
            ->method('find')
            ->with(ByValueDifferentiatorEntity::class, ['id' => $id])
            ->willReturn($entityInDatabase);

        $hydrator = $this->createHydrator($objectManager, $byValue);
        $entity   = $hydrator->hydrate(['id' => $id], new ByValueDifferentiatorEntity());

        $this->assertSame($entityInDatabase, $entity);
        $this->assertSame('bar', $entity->getField(false));
    }

    #[DataProvider('byValueProvider')]
    public function testExtractIsser(bool $byValue): void
    {
        $entity = new SimpleIsEntity();
        $entity->setId(2);
        $entity->setDone(true);

        $metadata = $this->createMetadata(SimpleIsEntity::class, ['id' => 'integer', 'done' => 'boolean']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        // By value, isDone() is used as there is no getter
        $this->assertSame(['id' => 2, 'done' => true], $hydrator->extract($entity));
    }

    #[DataProvider('byValueProvider')]
    public function testExtractIsserThatStartsWithIs(bool $byValue): void
    {
        $entity = new SimpleEntityWithIsBoolean();
        $entity->setId(2);
        $entity->setIsActive(true);

        $metadata = $this->createMetadata(SimpleEntityWithIsBoolean::class, ['id' => 'integer', 'isActive' => 'boolean']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        $this->assertSame(['id' => 2, 'isActive' => true], $hydrator->extract($entity));
    }

    #[DataProvider('byValueProvider')]
    public function testExtractUninitializedProperties(bool $byValue): void
    {
        $entity = new SimpleEntityWithUninitializedProperties();
        $entity->setId(2);

        $metadata = $this->createMetadata(SimpleEntityWithUninitializedProperties::class, ['id' => 'integer', 'field' => 'string']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        if ($byValue) {
            // Documents the current behaviour: the getter accesses the uninitialized property
            $this->expectException(Error::class);
            $this->expectExceptionMessage('must not be accessed before initialization');
        }

        // By reference, uninitialized properties are skipped
        $this->assertSame(['id' => 2], $hydrator->extract($entity));
    }

    #[DataProvider('byValueProvider')]
    public function testExtractPropertiesAfterInitialization(bool $byValue): void
    {
        $entity = new SimpleEntityWithUninitializedProperties();
        $entity->setId(2);
        $entity->setField('value');

        $metadata = $this->createMetadata(SimpleEntityWithUninitializedProperties::class, ['id' => 'integer', 'field' => 'string']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        $this->assertSame(['id' => 2, 'field' => 'value'], $hydrator->extract($entity));
    }

    #[DataProvider('byValueProvider')]
    public function testHydratePrivateAndProtectedMembers(bool $byValue): void
    {
        $metadata = $this->createMetadata(
            SimplePrivateEntity::class,
            ['private' => 'integer', 'protected' => 'integer'],
            identifier: ['private'],
        );
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        // By value, the non-public setters are not called, as they would throw an exception
        $entity = $hydrator->hydrate(['private' => 123, 'protected' => 456], new SimplePrivateEntity());

        // By reference, the properties are written directly regardless of their visibility
        $this->assertSame($byValue ? null : 123, (new ReflectionProperty($entity, 'private'))->getValue($entity));
        $this->assertSame($byValue ? null : 456, (new ReflectionProperty($entity, 'protected'))->getValue($entity));
    }
}
