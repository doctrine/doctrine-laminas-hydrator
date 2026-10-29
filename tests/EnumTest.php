<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithEnum;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEnum;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEnumStrategy;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;

#[CoversClass(DoctrineObject::class)]
class EnumTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testHydrateEnumUsingStrategy(bool $byValue): void
    {
        $entity = $this->createEnumHydrator($byValue)->hydrate(['enum' => 1], new SimpleEntityWithEnum());

        $this->assertSame(SimpleEnum::One, $entity->getEnum());
    }

    #[DataProvider('byValueProvider')]
    public function testNullIsNotConvertedToEnum(bool $byValue): void
    {
        $entity = new SimpleEntityWithEnum();
        $entity->setEnum(SimpleEnum::Two);

        $entity = $this->createEnumHydrator($byValue)->hydrate(['enum' => null], $entity);

        $this->assertNull($entity->getEnum());
    }

    #[DataProvider('byValueProvider')]
    public function testInvalidBackingValueThrowsException(bool $byValue): void
    {
        $this->expectException(TypeError::class);

        $this->createEnumHydrator($byValue)->hydrate(['enum' => 'string'], new SimpleEntityWithEnum());
    }

    private function createEnumHydrator(bool $byValue): DoctrineObject
    {
        $metadata = $this->createMetadata(SimpleEntityWithEnum::class, ['id' => 'integer', 'enum' => 'integer']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);
        $hydrator->addStrategy('enum', new SimpleEnumStrategy());

        return $hydrator;
    }
}
