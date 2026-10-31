<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\ByValueDifferentiatorEntity;
use DoctrineTest\Laminas\Hydrator\Assets\OneToManyArrayEntity;
use DoctrineTest\Laminas\Hydrator\Assets\OneToManyEntity;
use DoctrineTest\Laminas\Hydrator\Assets\OneToManyEntityWithEntities;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

#[CoversClass(DoctrineObject::class)]
class ToManyAssociationTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testExtract(bool $byValue): void
    {
        [$toMany1, $toMany2] = $this->createEntities();

        $entity = new OneToManyEntity();
        $entity->setId(4);
        $entity->addEntities(new ArrayCollection([$toMany1, $toMany2]));

        $data = $this->createHydrator($this->createToManyObjectManager(OneToManyEntity::class), $byValue)->extract($entity);

        $this->assertSame(4, $data['id']);
        $this->assertInstanceOf(Collection::class, $data['entities']);
        $this->assertSame([$toMany1, $toMany2], $data['entities']->toArray());
    }

    #[DataProvider('byValueProvider')]
    public function testExtractWithGetterReturningArray(bool $byValue): void
    {
        [$toMany1, $toMany2] = $this->createEntities();

        $entity = new OneToManyArrayEntity();
        $entity->setId(4);
        $entity->addEntities(new ArrayCollection([$toMany1, $toMany2]));

        $data = $this->createHydrator($this->createToManyObjectManager(OneToManyArrayEntity::class), $byValue)->extract($entity);

        // By value, the getter returns an array, by reference the collection is read from the property
        $entities = $byValue ? $data['entities'] : $data['entities']->toArray();
        $this->assertSame(4, $data['id']);
        $this->assertSame([$toMany1, $toMany2], $entities);
    }

    /** @return iterable<string, array{bool, class-string<OneToManyEntity|OneToManyArrayEntity>}> */
    public static function entityClassProvider(): iterable
    {
        yield 'by value' => [true, OneToManyEntity::class];
        yield 'by value, getter returning array' => [true, OneToManyArrayEntity::class];
        yield 'by reference' => [false, OneToManyEntity::class];
        yield 'by reference, getter returning array' => [false, OneToManyArrayEntity::class];
    }

    /** @param class-string<OneToManyEntity|OneToManyArrayEntity> $className */
    #[DataProvider('entityClassProvider')]
    public function testHydrateWithEntities(bool $byValue, string $className): void
    {
        [$toMany1, $toMany2] = $this->createEntities();

        $entity = $this->createHydrator($this->createToManyObjectManager($className), $byValue)
            ->hydrate(['entities' => [$toMany1, $toMany2]], new $className());

        $this->assertCollectionContains($entity, [$toMany1, $toMany2], $byValue);
    }

    /** @return iterable<string, array{bool, list<int|array<string, int>>}> */
    public static function identifiersProvider(): iterable
    {
        yield 'by value, identifiers' => [true, [2, 3]];
        yield 'by value, identifier arrays' => [true, [['id' => 2], ['id' => 3]]];
        yield 'by reference, identifiers' => [false, [2, 3]];
        yield 'by reference, identifier arrays' => [false, [['id' => 2], ['id' => 3]]];
    }

    /** @param list<int|array<string, int>> $identifiers */
    #[DataProvider('identifiersProvider')]
    public function testHydrateWithIdentifiers(bool $byValue, array $identifiers): void
    {
        $entitiesInDatabase = $this->createEntities();

        $entity = $this->createHydrator($this->createToManyObjectManager(OneToManyEntity::class, $entitiesInDatabase), $byValue)
            ->hydrate(['entities' => $identifiers], new OneToManyEntity());

        $this->assertCollectionContains($entity, $entitiesInDatabase, $byValue);
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateWithObjectsHavingIdentifiers(bool $byValue): void
    {
        $entitiesInDatabase = $this->createEntities();

        $stdClass1     = new stdClass();
        $stdClass1->id = 2;
        $stdClass2     = new stdClass();
        $stdClass2->id = 3;

        $entity = $this->createHydrator($this->createToManyObjectManager(OneToManyEntity::class, $entitiesInDatabase), $byValue)
            ->hydrate(['entities' => [$stdClass1, $stdClass2]], new OneToManyEntity());

        $this->assertCollectionContains($entity, $entitiesInDatabase, $byValue);
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function traversableProvider(): iterable
    {
        yield 'by value, array' => [true, false];
        yield 'by value, traversable' => [true, true];
        yield 'by reference, array' => [false, false];
        yield 'by reference, traversable' => [false, true];
    }

    #[DataProvider('traversableProvider')]
    public function testHydrateWithArraysModifiesExistingEntities(bool $byValue, bool $traversable): void
    {
        $entitiesInDatabase = $this->createEntities();

        $values = [
            ['id' => 2, 'field' => 'Modified By Hydrate'],
            ['id' => 3, 'field' => 'Modified By Hydrate'],
        ];

        $entity = new OneToManyEntityWithEntities(new ArrayCollection($entitiesInDatabase));
        $entity = $this->createHydrator($this->createToManyObjectManager(OneToManyEntityWithEntities::class, $entitiesInDatabase), $byValue)
            ->hydrate(['entities' => $traversable ? new ArrayCollection($values) : $values], $entity);

        $this->assertSame($entitiesInDatabase, $entity->getEntities(false)->toArray());
        foreach ($entitiesInDatabase as $entityInDatabase) {
            $this->assertSame(
                $byValue ? 'From setter: Modified By Hydrate' : 'Modified By Hydrate',
                $entityInDatabase->getField(false),
            );
        }
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function collectionNotReplacedProvider(): iterable
    {
        yield 'by value, entities' => [true, false];
        yield 'by value, identifiers' => [true, true];
        yield 'by reference, entities' => [false, false];
        yield 'by reference, identifiers' => [false, true];
    }

    #[DataProvider('collectionNotReplacedProvider')]
    public function testCollectionIsNotReplacedDuringHydration(bool $byValue, bool $useIdentifiers): void
    {
        $entitiesInDatabase = $this->createEntities();

        $entity = new OneToManyEntity();
        $entity->addEntities(new ArrayCollection($entitiesInDatabase));
        $initialCollection = $entity->getEntities(false);

        $entity = $this->createHydrator($this->createToManyObjectManager(OneToManyEntity::class, $entitiesInDatabase), $byValue)
            ->hydrate(['entities' => $useIdentifiers ? [2, 3] : $entitiesInDatabase], $entity);

        $this->assertSame($initialCollection, $entity->getEntities(false));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateWithEmptyIdentifier(bool $byValue): void
    {
        $entityInDatabaseWithEmptyId = new ByValueDifferentiatorEntity();
        $entityInDatabaseWithEmptyId->setId('');
        $entityInDatabaseWithEmptyId->setField('baz', false);

        $entity = $this->createHydrator(
            $this->createToManyObjectManager(OneToManyEntity::class, [$entityInDatabaseWithEmptyId]),
            $byValue,
        )->hydrate(['entities' => ['']], new OneToManyEntity());

        $this->assertSame([$entityInDatabaseWithEmptyId], $entity->getEntities(false)->toArray());
    }

    /** @return list<ByValueDifferentiatorEntity> */
    private function createEntities(): array
    {
        $toMany1 = new ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        return [$toMany1, $toMany2];
    }

    /**
     * @param class-string $className
     * @param list<object> $entitiesInDatabase
     */
    private function createToManyObjectManager(string $className, array $entitiesInDatabase = []): ObjectManager
    {
        return $this->createObjectManager(
            [
                $this->createMetadata($className, ['id' => 'integer'], toMany: ['entities' => ByValueDifferentiatorEntity::class]),
                $this->createByValueDifferentiatorMetadata(),
            ],
            $entitiesInDatabase,
        );
    }

    /**
     * Asserts the collection contains the expected entities. By value, they are added using the adder of the entity.
     *
     * @param list<ByValueDifferentiatorEntity> $expected
     */
    private function assertCollectionContains(OneToManyEntity|OneToManyArrayEntity $entity, array $expected, bool $byValue): void
    {
        $entities = $entity->getEntities(false);
        $this->assertSame($expected, $entities instanceof Collection ? $entities->toArray() : $entities);

        foreach ($expected as $element) {
            if ($byValue) {
                $this->assertStringContainsString('Modified from addEntities adder', $element->getField(false));
            } else {
                $this->assertStringNotContainsString('Modified from addEntities adder', $element->getField(false));
            }
        }
    }
}
