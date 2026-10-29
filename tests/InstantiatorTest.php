<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use ArgumentCountError;
use Closure;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\EntityWithConstructorArgument;
use DoctrineTest\Laminas\Hydrator\Assets\OwnerOfEntitiesWithConstructorArgument;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use UnexpectedValueException;

#[CoversClass(DoctrineObject::class)]
class InstantiatorTest extends HydratorTestCase
{
    /** @var list<array{class-string, array<array-key, mixed>}> */
    private array $instantiatorCalls = [];

    #[DataProvider('byValueProvider')]
    public function testNewEntitiesAreCreatedUsingTheConstructorByDefault(bool $byValue): void
    {
        $hydrator = new DoctrineObject($this->createInstantiatorObjectManager(), $byValue);

        // The constructor of the associated entity requires an argument
        $this->expectException(ArgumentCountError::class);

        $hydrator->hydrate(['toOne' => ['id' => 5, 'field' => 'foo']], new OwnerOfEntitiesWithConstructorArgument());
    }

    #[DataProvider('byValueProvider')]
    public function testNewToOneEntityIsCreatedUsingInstantiator(bool $byValue): void
    {
        $hydrator = new DoctrineObject($this->createInstantiatorObjectManager(), $byValue, instantiator: $this->createInstantiator());

        $entity = $hydrator->hydrate(['toOne' => ['id' => 5, 'field' => 'foo']], new OwnerOfEntitiesWithConstructorArgument());

        $this->assertSame([[EntityWithConstructorArgument::class, ['id' => 5, 'field' => 'foo']]], $this->instantiatorCalls);
        $this->assertSame('instantiator', $entity->getToOne()?->getOrigin());
        $this->assertSame(5, $entity->getToOne()?->getId());
        $this->assertSame('foo', $entity->getToOne()?->getField());
    }

    #[DataProvider('byValueProvider')]
    public function testNewToManyEntitiesAreCreatedUsingInstantiator(bool $byValue): void
    {
        $hydrator = new DoctrineObject($this->createInstantiatorObjectManager(), $byValue, instantiator: $this->createInstantiator());

        $entity = $hydrator->hydrate(
            ['entities' => [['id' => 5, 'field' => 'foo'], ['id' => 6, 'field' => 'bar']]],
            new OwnerOfEntitiesWithConstructorArgument(),
        );

        $this->assertSame(
            [
                [EntityWithConstructorArgument::class, ['id' => 5, 'field' => 'foo']],
                [EntityWithConstructorArgument::class, ['id' => 6, 'field' => 'bar']],
            ],
            $this->instantiatorCalls,
        );

        $entities = $entity->getEntities()->toArray();
        $this->assertCount(2, $entities);
        $this->assertSame(['instantiator', 'instantiator'], [$entities[0]->getOrigin(), $entities[1]->getOrigin()]);
        $this->assertSame(['foo', 'bar'], [$entities[0]->getField(), $entities[1]->getField()]);
    }

    #[DataProvider('byValueProvider')]
    public function testNewToManyEntityWithoutDataIsCreatedUsingInstantiator(bool $byValue): void
    {
        $hydrator = new DoctrineObject($this->createInstantiatorObjectManager(), $byValue, instantiator: $this->createInstantiator());

        // An identifier of an entity that does not exist
        $entity = $hydrator->hydrate(['entities' => [5]], new OwnerOfEntitiesWithConstructorArgument());

        $this->assertSame([[EntityWithConstructorArgument::class, []]], $this->instantiatorCalls);
        $this->assertCount(1, $entity->getEntities());
    }

    #[DataProvider('byValueProvider')]
    public function testInstantiatorIsNotUsedForExistingEntities(bool $byValue): void
    {
        $existing = new EntityWithConstructorArgument('database');
        $existing->setId(5);

        $hydrator = new DoctrineObject(
            $this->createInstantiatorObjectManager([$existing]),
            $byValue,
            instantiator: $this->createInstantiator(),
        );

        $entity = $hydrator->hydrate(['toOne' => ['id' => 5, 'field' => 'foo']], new OwnerOfEntitiesWithConstructorArgument());

        $this->assertSame([], $this->instantiatorCalls);
        $this->assertSame($existing, $entity->getToOne());
    }

    #[DataProvider('byValueProvider')]
    public function testInstantiatorMustReturnInstanceOfTheClass(bool $byValue): void
    {
        $hydrator = new DoctrineObject(
            $this->createInstantiatorObjectManager(),
            $byValue,
            instantiator: static fn (string $className, array $data): object => new stdClass(),
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'The instantiator must return an instance of "' . EntityWithConstructorArgument::class . '", "stdClass" returned',
        );

        $hydrator->hydrate(['toOne' => ['id' => 5, 'field' => 'foo']], new OwnerOfEntitiesWithConstructorArgument());
    }

    /** @return Closure(class-string, array<array-key, mixed>): object */
    private function createInstantiator(): Closure
    {
        return function (string $className, array $data): object {
            $this->instantiatorCalls[] = [$className, $data];

            return new $className('instantiator');
        };
    }

    /** @param list<object> $entitiesInDatabase */
    private function createInstantiatorObjectManager(array $entitiesInDatabase = []): ObjectManager
    {
        return $this->createObjectManager(
            [
                $this->createMetadata(
                    OwnerOfEntitiesWithConstructorArgument::class,
                    ['id' => 'integer'],
                    toOne: ['toOne' => EntityWithConstructorArgument::class],
                    toMany: ['entities' => EntityWithConstructorArgument::class],
                ),
                $this->createMetadata(EntityWithConstructorArgument::class, ['id' => 'integer', 'field' => 'string']),
            ],
            $entitiesInDatabase,
        );
    }
}
