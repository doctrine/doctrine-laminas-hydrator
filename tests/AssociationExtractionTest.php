<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Common\Collections\Collection;
use Doctrine\Laminas\Hydrator\AssociationExtraction;
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineObjectHydrator;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\TreeNodeEntity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_flip;
use function array_intersect_key;

#[CoversMethod(DoctrineObjectHydrator::class, 'extract')]
#[CoversMethod(DoctrineObjectHydrator::class, 'setAssociationExtraction')]
class AssociationExtractionTest extends TestCase
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

    /** @return iterable<string, array{bool}> */
    public static function byValueProvider(): iterable
    {
        yield 'by value' => [true];
        yield 'by reference' => [false];
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationsAreExtractedAsObjectsByDefault(bool $byValue): void
    {
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager(), $byValue);

        $this->assertSame(AssociationExtraction::Object, $hydrator->getAssociationExtraction());

        $data = $hydrator->extract($this->child);

        $this->assertSame($this->root, $data['parent']);
        $this->assertInstanceOf(Collection::class, $data['children']);
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationsAreExtractedAsIdentifiers(bool $byValue): void
    {
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager(), $byValue);
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
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager(['id', 'name']), $byValue);
        $hydrator->setAssociationExtraction(AssociationExtraction::Identifier);

        $data = $hydrator->extract($this->child);

        $this->assertSame(['id' => 1, 'name' => 'root'], $data['parent']);
        $this->assertSame([['id' => 4, 'name' => 'grandchild']], $data['children']);
    }

    #[DataProvider('byValueProvider')]
    public function testAssociationsAreExtractedAsArraysUpToMaxDepth(bool $byValue): void
    {
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager(), $byValue);
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
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager(), $byValue);
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

    public function testHydratorCanBeReusedAfterArrayExtraction(): void
    {
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager());
        $hydrator->setAssociationExtraction(AssociationExtraction::Array);

        $hydrator->extract($this->child);

        $this->assertSame(
            ['id' => 2, 'name' => 'child', 'parent' => 1, 'children' => [4]],
            $hydrator->extract($this->root)['children'][0],
        );
    }

    public function testValuesConvertedByStrategiesAreLeftUntouched(): void
    {
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager());
        $hydrator->setAssociationExtraction(AssociationExtraction::Identifier);
        $hydrator->addStrategy('parent', new Assets\SimpleStrategy());

        $this->assertSame('modified while extracting', $hydrator->extract($this->child)['parent']);
    }

    public function testMaxDepthMustBePositive(): void
    {
        $hydrator = new DoctrineObjectHydrator($this->createObjectManager());

        $this->expectException(InvalidArgumentException::class);

        $hydrator->setAssociationExtraction(AssociationExtraction::Array, 0);
    }

    /** @param list<string> $identifierFields */
    private function createObjectManager(array $identifierFields = ['id']): ObjectManager
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn(TreeNodeEntity::class);
        $metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $metadata->method('getAssociationNames')->willReturn(['parent', 'children']);
        $metadata->method('hasAssociation')->willReturnMap([
            ['id', false],
            ['name', false],
            ['parent', true],
            ['children', true],
        ]);
        $metadata->method('isSingleValuedAssociation')->willReturnMap([
            ['parent', true],
            ['children', false],
        ]);
        $metadata->method('isCollectionValuedAssociation')->willReturnMap([
            ['parent', false],
            ['children', true],
        ]);
        $metadata->method('getAssociationTargetClass')->willReturn(TreeNodeEntity::class);
        $metadata->method('getReflectionClass')->willReturn(new ReflectionClass(TreeNodeEntity::class));
        $metadata->method('getIdentifier')->willReturn($identifierFields);
        $metadata->method('getIdentifierFieldNames')->willReturn($identifierFields);
        $metadata->method('getIdentifierValues')->willReturnCallback(
            static function (TreeNodeEntity $node) use ($identifierFields): array {
                $values = ['id' => $node->getId(), 'name' => $node->getName()];

                return array_intersect_key($values, array_flip($identifierFields));
            },
        );

        $objectManager = $this->createStub(ObjectManager::class);
        $objectManager->method('getClassMetadata')->willReturn($metadata);

        return $objectManager;
    }
}
