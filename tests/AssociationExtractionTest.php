<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Common\Collections\Collection;
use Doctrine\Laminas\Hydrator\AssociationExtraction;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\TreeNodeEntity;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DoctrineObject::class)]
class AssociationExtractionTest extends HydratorTestCase
{
    private TreeNodeEntity $root;

    private TreeNodeEntity $child;

    protected function setUp(): void
    {
        parent::setUp();

        // root (1) -> child (2) -> grandchild (4)
        //          -> sibling (3)
        $this->root  = new TreeNodeEntity(1, 'root');
        $this->child = new TreeNodeEntity(2, 'child');
        $this->root->addChild($this->child);
        $this->root->addChild(new TreeNodeEntity(3, 'sibling'));
        $this->child->addChild(new TreeNodeEntity(4, 'grandchild'));
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationsAreExtractedAsObjectsByDefault(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(), $byValue);

        $this->assertSame(AssociationExtraction::Object, $hydrator->getAssociationExtraction());

        $data = $hydrator->extract($this->child);

        $this->assertSame($this->root, $data['parent']);
        $this->assertInstanceOf(Collection::class, $data['children']);
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationsAreExtractedAsIdentifiers(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Identifier);

        $this->assertSame(
            ['id' => 2, 'name' => 'child', 'parent' => 1, 'children' => [4]],
            $hydrator->extract($this->child),
        );
        $this->assertSame(
            ['id' => 1, 'name' => 'root', 'parent' => null, 'children' => [2, 3]],
            $hydrator->extract($this->root),
        );
    }

    #[DataProvider('byValueProvider')]
    public function testCompositeIdentifiersAreExtractedAsArrays(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(['id', 'name']), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Identifier);

        $data = $hydrator->extract($this->child);

        $this->assertSame(['id' => 1, 'name' => 'root'], $data['parent']);
        $this->assertSame([['id' => 4, 'name' => 'grandchild']], $data['children']);
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationsAreExtractedAsArraysUpToMaxDepth(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Array);

        $this->assertSame(1, $hydrator->getAssociationExtractionMaxDepth());
        $this->assertSame(
            [
                'id' => 1,
                'name' => 'root',
                'parent' => null,
                'children' => [
                    ['id' => 2, 'name' => 'child', 'parent' => 1, 'children' => [4]],
                    ['id' => 3, 'name' => 'sibling', 'parent' => 1, 'children' => []],
                ],
            ],
            $hydrator->extract($this->root),
        );
    }

    #[DataProvider('byValueProvider')]
    public function testCircularReferencesAreExtractedAsIdentifiers(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Array, 10);

        $this->assertSame(
            [
                'id' => 2,
                'name' => 'child',
                'parent' => [
                    'id' => 1,
                    'name' => 'root',
                    'parent' => null,
                    'children' => [
                        2,
                        ['id' => 3, 'name' => 'sibling', 'parent' => 1, 'children' => []],
                    ],
                ],
                'children' => [
                    ['id' => 4, 'name' => 'grandchild', 'parent' => 2, 'children' => []],
                ],
            ],
            $hydrator->extract($this->child),
        );
    }

    #[DataProvider('byValueProvider')]
    public function testHydratorCanBeReusedAfterArrayExtraction(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Array);

        $hydrator->extract($this->child);

        $this->assertSame(
            ['id' => 2, 'name' => 'child', 'parent' => 1, 'children' => [4]],
            $hydrator->extract($this->root)['children'][0],
        );
    }

    #[DataProvider('byValueProvider')]
    public function testValuesConvertedByStrategiesAreLeftUntouched(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Identifier);
        $hydrator->addStrategy('parent', new Assets\SimpleStrategy());

        $this->assertSame('modified while extracting', $hydrator->extract($this->child)['parent']);
    }

    public function testMaxDepthMustBePositive(): void
    {
        $hydrator = $this->createHydrator($this->createTreeObjectManager(), true);

        $this->expectException(InvalidArgumentException::class);

        $hydrator->setAssociationExtraction(AssociationExtraction::Array, 0);
    }

    /** @param list<string> $identifier */
    private function createTreeObjectManager(array $identifier = ['id']): ObjectManager
    {
        return $this->createObjectManager([
            $this->createMetadata(
                TreeNodeEntity::class,
                ['id' => 'integer', 'name' => 'string'],
                toOne: ['parent' => TreeNodeEntity::class],
                toMany: ['children' => TreeNodeEntity::class],
                identifier: $identifier,
            ),
        ]);
    }
}
