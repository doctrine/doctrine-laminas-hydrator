<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Persistence\Mapping\ClassMetadata;
use DoctrineTest\Laminas\Hydrator\Assets\ByValueDifferentiatorEntity;
use DoctrineTest\Laminas\Hydrator\Assets\OneToOneEntity;
use DoctrineTest\Laminas\Hydrator\Assets\OneToOneEntityNotNullable;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DoctrineObject::class)]
class ToOneAssociationTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testExtract(bool $byValue): void
    {
        $toOne = new ByValueDifferentiatorEntity();
        $toOne->setId(2);
        $toOne->setField('foo', false);

        $entity = new OneToOneEntity();
        $entity->setId(2);
        $entity->setToOne($toOne, false);

        $hydrator = $this->createHydrator(
            $this->createObjectManager([$this->createOneToOneMetadata(), $this->createByValueDifferentiatorMetadata()]),
            $byValue,
        );

        $data = $hydrator->extract($entity);

        $this->assertSame(2, $data['id']);
        $this->assertSame($toOne, $data['toOne']);
        // By value, the getter modifies the associated entity
        $this->assertSame($byValue ? 'Modified from getToOne getter' : 'foo', $toOne->getField(false));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateWithEntity(bool $byValue): void
    {
        $toOne = new ByValueDifferentiatorEntity();
        $toOne->setId(2);
        $toOne->setField('foo', false);

        $hydrator = $this->createHydrator(
            $this->createObjectManager([$this->createOneToOneMetadata(), $this->createByValueDifferentiatorMetadata()]),
            $byValue,
        );

        $entity = $hydrator->hydrate(['toOne' => $toOne], new OneToOneEntity());

        $this->assertSame($toOne, $entity->getToOne(false));
        // By value, the setter modifies the associated entity
        $this->assertSame($byValue ? 'Modified from setToOne setter' : 'foo', $toOne->getField(false));
    }

    /** @return iterable<string, array{bool, int|array<string, int>}> */
    public static function identifierProvider(): iterable
    {
        yield 'by value, identifier' => [true, 1];
        yield 'by value, identifier array' => [true, ['id' => 1]];
        yield 'by reference, identifier' => [false, 1];
        yield 'by reference, identifier array' => [false, ['id' => 1]];
    }

    /** @param int|array<string, int> $identifier */
    #[DataProvider('identifierProvider')]
    public function testHydrateWithIdentifier(bool $byValue, int|array $identifier): void
    {
        $entityInDatabase = new ByValueDifferentiatorEntity();
        $entityInDatabase->setId(1);
        $entityInDatabase->setField('bar', false);

        $objectManager = $this->createObjectManagerMock([
            $this->createOneToOneMetadata(),
            $this->createByValueDifferentiatorMetadata(),
        ]);
        $objectManager
            ->expects($this->once())
            ->method('find')
            ->with(ByValueDifferentiatorEntity::class, $identifier)
            ->willReturn($entityInDatabase);

        $entity = $this->createHydrator($objectManager, $byValue)->hydrate(['toOne' => $identifier], new OneToOneEntity());

        $this->assertSame($entityInDatabase, $entity->getToOne(false));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateWithArrayOfExistingEntity(bool $byValue): void
    {
        $entityInDatabase = new ByValueDifferentiatorEntity();
        $entityInDatabase->setId(1);
        $entityInDatabase->setField('bar', false);

        $objectManager = $this->createObjectManager(
            [
                $this->createMetadata(
                    OneToOneEntityNotNullable::class,
                    ['id' => 'integer'],
                    toOne: ['toOne' => ByValueDifferentiatorEntity::class],
                ),
                $this->createByValueDifferentiatorMetadata(),
            ],
            [$entityInDatabase],
        );

        $entity = $this->createHydrator($objectManager, $byValue)
            ->hydrate(['toOne' => ['id' => 1, 'field' => 'foo']], new OneToOneEntityNotNullable());

        // The existing entity is hydrated with the data. By value, the setter of the owning entity modifies it afterwards
        $this->assertSame($entityInDatabase, $entity->getToOne(false));
        $this->assertSame($byValue ? 'Modified from setToOne setter' : 'foo', $entityInDatabase->getField(false));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateWithArrayOfNewEntity(bool $byValue): void
    {
        $hydrator = $this->createHydrator(
            $this->createObjectManager([
                $this->createOneToOneMetadata(withCreatedAt: true),
                $this->createByValueDifferentiatorMetadata(),
            ]),
            $byValue,
        );

        $entity = new OneToOneEntity();
        $data   = [
            'id'        => 12,
            'toOne'     => [
                'id'    => 13,
                'field' => 'value',
            ],
            'createdAt' => '2019-01-24 12:00:00',
        ];

        $hydrator->hydrate($data, $entity);

        // The associated entity does not exist, so a new one is created and hydrated with the data
        $this->assertSame(12, $entity->getId());
        $this->assertInstanceOf(ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertSame(13, $entity->getToOne(false)->getId());
        $this->assertSame($byValue ? 'Modified from setToOne setter' : 'value', $entity->getToOne(false)->getField(false));
        $this->assertSame('2019-01-24 12:00:00', $entity->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateNull(bool $byValue): void
    {
        $entity = new OneToOneEntity();
        $entity->setToOne(new ByValueDifferentiatorEntity(), false);

        $objectManager = $this->createObjectManagerMock([
            $this->createOneToOneMetadata(),
            $this->createByValueDifferentiatorMetadata(),
        ]);
        $objectManager->expects($this->never())->method('find');

        $entity = $this->createHydrator($objectManager, $byValue)->hydrate(['toOne' => null], $entity);

        $this->assertNull($entity->getToOne(false));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateNullIsIgnoredIfNotAccepted(bool $byValue): void
    {
        $toOne  = new ByValueDifferentiatorEntity();
        $entity = new OneToOneEntityNotNullable();
        $entity->setToOne($toOne, false);

        $objectManager = $this->createObjectManagerMock([
            $this->createMetadata(
                OneToOneEntityNotNullable::class,
                ['id' => 'integer'],
                toOne: ['toOne' => ByValueDifferentiatorEntity::class],
            ),
            $this->createByValueDifferentiatorMetadata(),
        ]);
        $objectManager->expects($this->never())->method('find');

        // Null is ignored as the setter (by value) or the property (by reference) does not accept it
        $entity = $this->createHydrator($objectManager, $byValue)->hydrate(['toOne' => null], $entity);

        $this->assertSame($toOne, $entity->getToOne(false));
    }

    private function createOneToOneMetadata(bool $withCreatedAt = false): ClassMetadata
    {
        $fields = $withCreatedAt ? ['id' => 'integer', 'createdAt' => 'datetime'] : ['id' => 'integer'];

        return $this->createMetadata(OneToOneEntity::class, $fields, toOne: ['toOne' => ByValueDifferentiatorEntity::class]);
    }
}
