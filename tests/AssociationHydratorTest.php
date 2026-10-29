<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\AssociationExtraction;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Laminas\Hydrator\Filter\PropertyName;
use Doctrine\Laminas\Hydrator\Strategy\DisallowRemoveByValue;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\ChildEntity;
use DoctrineTest\Laminas\Hydrator\Assets\ParentEntity;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleStrategy;
use DoctrineTest\Laminas\Hydrator\Assets\TreeNodeEntity;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use InvalidArgumentException;
use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DoctrineObject::class)]
class AssociationHydratorTest extends HydratorTestCase
{
    /** Data with a nested child for the to-one and the to-many association */
    private const array DATA = [
        'name' => 'parent',
        'child' => ['name' => 'child'],
        'children' => [['name' => 'first'], ['name' => 'second']],
    ];

    #[DataProvider('byValueProvider')]
    public function testStrategiesOfTheRootDoNotApplyToAssociatedEntities(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createParentObjectManager(), $byValue);
        $hydrator->addStrategy('name', new SimpleStrategy());

        $entity = $hydrator->hydrate(self::DATA, new ParentEntity());

        $this->assertSame('modified while hydrating', $entity->getName());
        $this->assertSame('child', $entity->getChild()?->getName());
        $this->assertSame(['first', 'second'], $this->getChildNames($entity));
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationHydratorIsUsedForHydration(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createParentObjectManager(), $byValue);
        $hydrator->getAssociationHydrator(ChildEntity::class)->addStrategy('name', new SimpleStrategy());

        $entity = $hydrator->hydrate(self::DATA, new ParentEntity());

        $this->assertSame('parent', $entity->getName());
        $this->assertSame('modified while hydrating', $entity->getChild()?->getName());
        $this->assertSame(['modified while hydrating', 'modified while hydrating'], $this->getChildNames($entity));
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationHydratorIsUsedForExtraction(bool $byValue): void
    {
        $entity = $this->createParentEntity();

        $hydrator = $this->createHydrator($this->createParentObjectManager(), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Array);
        $hydrator->addFilter('id', new PropertyName(['id']));
        $hydrator->getAssociationHydrator(ChildEntity::class)->addFilter('name', new PropertyName(['name']));

        $data = $hydrator->extract($entity);

        // The filter of the root only applies to the parent, the filter of the association hydrator to the children
        $this->assertArrayNotHasKey('id', $data);
        $this->assertSame('parent', $data['name']);
        $this->assertSame(['id' => 2, 'displayName' => null, 'code' => null], $data['child']);
        $this->assertSame([3, 4], [$data['children'][0]['id'], $data['children'][1]['id']]);
        $this->assertArrayNotHasKey('name', $data['children'][0]);
    }

    public function testAssociationHydratorIsUsedAtAllLevels(): void
    {
        // root (1) -> child (2) -> grandchild (4)
        $root  = new TreeNodeEntity(1, 'root');
        $child = new TreeNodeEntity(2, 'child');
        $root->addChild($child);
        $child->addChild(new TreeNodeEntity(4, 'grandchild'));

        $hydrator = $this->createHydrator($this->createTreeObjectManager(), false);
        $hydrator->setAssociationExtraction(AssociationExtraction::Array, 2);
        $hydrator->getAssociationHydrator(TreeNodeEntity::class)->addFilter('name', new PropertyName(['name']));

        $data = $hydrator->extract($root);

        // The root itself is extracted by the root hydrator, all associated nodes by the association hydrator
        $this->assertSame('root', $data['name']);
        $this->assertSame(['id' => 2, 'parent' => 1, 'children' => [['id' => 4, 'parent' => 2, 'children' => []]]], $data['children'][0]);
    }

    #[DataProvider('byValueProvider')]
    public function testNamingStrategyIsInherited(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createParentObjectManager(), $byValue);

        // The association hydrator inherits the naming strategy even if it is set on the root afterwards
        $hydrator->getAssociationHydrator(ChildEntity::class);
        $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());

        $entity = $hydrator->hydrate(['child' => ['display_name' => 'Child']], new ParentEntity());
        $this->assertSame('Child', $entity->getChild()?->getDisplayName());

        $hydrator->setAssociationExtraction(AssociationExtraction::Array);
        $this->assertArrayHasKey('display_name', $hydrator->extract($entity)['child']);
    }

    #[DataProvider('byValueProvider')]
    public function testNamingStrategyOfAssociationHydratorIsKept(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createParentObjectManager(), $byValue);
        $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());
        $hydrator->getAssociationHydrator(ChildEntity::class)->removeNamingStrategy();

        $entity = $hydrator->hydrate(['child' => ['displayName' => 'Child']], new ParentEntity());

        $this->assertSame('Child', $entity->getChild()?->getDisplayName());
    }

    public function testSettingsAreInherited(): void
    {
        $hydrator            = $this->createHydrator($this->createParentObjectManager(), true);
        $associationHydrator = $hydrator->getAssociationHydrator(ChildEntity::class);
        $hydrator->setDefaultByValueStrategy(DisallowRemoveByValue::class);

        $hydrator->hydrate(self::DATA, new ParentEntity());

        $this->assertSame($associationHydrator, $hydrator->getAssociationHydrator(ChildEntity::class));
        $this->assertSame(DisallowRemoveByValue::class, $associationHydrator->getDefaultByValueStrategy());
    }

    public function testAssociationHydratorCanBeReplaced(): void
    {
        $objectManager = $this->createParentObjectManager();

        // The root hydrates by value, the children by reference
        $hydrator = $this->createHydrator($objectManager, true);
        $hydrator->setAssociationHydrator(ChildEntity::class, $this->createHydrator($objectManager, false));

        $entity = $hydrator->hydrate(['child' => ['name' => 'child', 'code' => 'A1']], new ParentEntity());

        $this->assertSame('A1', $entity->getChild()?->getCode());
    }

    public function testAssociationHydratorMustNotBeTheRoot(): void
    {
        $hydrator = $this->createHydrator($this->createParentObjectManager(), true);

        $this->expectException(InvalidArgumentException::class);

        $hydrator->setAssociationHydrator(ChildEntity::class, $hydrator);
    }

    public function testAssociationHydratorMustNotHaveAssociationHydrators(): void
    {
        $objectManager       = $this->createParentObjectManager();
        $hydrator            = $this->createHydrator($objectManager, true);
        $associationHydrator = $this->createHydrator($objectManager, true);
        $associationHydrator->getAssociationHydrator(ParentEntity::class);

        $this->expectException(InvalidArgumentException::class);

        $hydrator->setAssociationHydrator(ChildEntity::class, $associationHydrator);
    }

    private function createParentEntity(): ParentEntity
    {
        $entity = new ParentEntity();
        $entity->setId(1);
        $entity->setName('parent');

        $child = new ChildEntity();
        $child->setId(2);
        $child->setName('child');
        $entity->setChild($child);

        foreach ([3 => 'first', 4 => 'second'] as $id => $name) {
            $child = new ChildEntity();
            $child->setId($id);
            $child->setName($name);
            $entity->getChildren()->add($child);
        }

        return $entity;
    }

    /** @return list<string|null> */
    private function getChildNames(ParentEntity $entity): array
    {
        $names = [];
        foreach ($entity->getChildren() as $child) {
            $names[] = $child->getName();
        }

        return $names;
    }

    private function createParentObjectManager(): ObjectManager
    {
        return $this->createObjectManager([
            $this->createMetadata(
                ParentEntity::class,
                ['id' => 'integer', 'name' => 'string'],
                toOne: ['child' => ChildEntity::class],
                toMany: ['children' => ChildEntity::class],
            ),
            $this->createMetadata(
                ChildEntity::class,
                ['id' => 'integer', 'name' => 'string', 'displayName' => 'string', 'code' => 'string'],
            ),
        ]);
    }

    private function createTreeObjectManager(): ObjectManager
    {
        return $this->createObjectManager([
            $this->createMetadata(
                TreeNodeEntity::class,
                ['id' => 'integer', 'name' => 'string'],
                toOne: ['parent' => TreeNodeEntity::class],
                toMany: ['children' => TreeNodeEntity::class],
            ),
        ]);
    }
}
