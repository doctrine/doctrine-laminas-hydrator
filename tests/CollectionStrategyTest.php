<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByReference;
use Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByValue;
use Doctrine\Laminas\Hydrator\Strategy\DisallowRemoveByReference;
use Doctrine\Laminas\Hydrator\Strategy\DisallowRemoveByValue;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\ByValueDifferentiatorEntity;
use DoctrineTest\Laminas\Hydrator\Assets\DifferentAllowRemoveByReference;
use DoctrineTest\Laminas\Hydrator\Assets\DifferentAllowRemoveByValue;
use DoctrineTest\Laminas\Hydrator\Assets\OneToManyEntity;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DoctrineObject::class)]
class CollectionStrategyTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testDefaultStrategy(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createToManyObjectManager(), $byValue);
        $hydrator->hydrate([], new OneToManyEntity());

        $expected = $byValue ? AllowRemoveByValue::class : AllowRemoveByReference::class;
        $this->assertSame(
            $expected,
            $byValue ? $hydrator->getDefaultByValueStrategy() : $hydrator->getDefaultByReferenceStrategy(),
        );
        $this->assertInstanceOf($expected, $hydrator->getStrategy('entities'));
    }

    #[DataProvider('byValueProvider')]
    public function testOverrideDefaultStrategy(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createToManyObjectManager(), $byValue);
        $hydrator->setDefaultByValueStrategy(DifferentAllowRemoveByValue::class);
        $hydrator->setDefaultByReferenceStrategy(DifferentAllowRemoveByReference::class);
        $hydrator->hydrate([], new OneToManyEntity());

        $expected = $byValue ? DifferentAllowRemoveByValue::class : DifferentAllowRemoveByReference::class;
        $this->assertSame(
            $expected,
            $byValue ? $hydrator->getDefaultByValueStrategy() : $hydrator->getDefaultByReferenceStrategy(),
        );
        $this->assertInstanceOf($expected, $hydrator->getStrategy('entities'));
    }

    #[DataProvider('byValueProvider')]
    public function testDisallowRemoveStrategyKeepsExistingElements(bool $byValue): void
    {
        $toMany1 = new ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $toMany3 = new ByValueDifferentiatorEntity();
        $toMany3->setId(8);
        $toMany3->setField('baz', false);

        // Initially add two elements
        $entity = new OneToManyEntity();
        $entity->addEntities(new ArrayCollection([$toMany1, $toMany2]), false);

        $hydrator = $this->createHydrator($this->createToManyObjectManager(), $byValue);
        $hydrator->addStrategy('entities', $byValue ? new DisallowRemoveByValue() : new DisallowRemoveByReference());

        // The hydrated collection contains one of the elements and a new one, the other element is missing
        $entity = $hydrator->hydrate(['entities' => [$toMany2, $toMany3]], $entity);

        // The missing element is not removed
        $this->assertSame([$toMany1, $toMany2, $toMany3], $entity->getEntities(false)->toArray());

        // By value, the new element is added using the adder
        if ($byValue) {
            $this->assertStringContainsString('Modified from addEntities adder', $toMany3->getField(false));
        } else {
            $this->assertStringNotContainsString('Modified from addEntities adder', $toMany3->getField(false));
        }
    }

    private function createToManyObjectManager(): ObjectManager
    {
        return $this->createObjectManager([
            $this->createMetadata(OneToManyEntity::class, ['id' => 'integer'], toMany: ['entities' => ByValueDifferentiatorEntity::class]),
            $this->createByValueDifferentiatorMetadata(),
        ]);
    }
}
