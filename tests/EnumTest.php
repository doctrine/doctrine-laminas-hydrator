<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;
use Doctrine\Persistence\ObjectManager;
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

    #[DataProvider('byValueProvider')]
    public function testHydrateEnumFromFieldMapping(bool $byValue): void
    {
        // Form data contains strings, even for enums backed by integers
        $entity = $this->createHydrator($this->createEnumObjectManager(), $byValue)
            ->hydrate(['enum' => '2'], new SimpleEntityWithEnum());

        $this->assertSame(SimpleEnum::Two, $entity->getEnum());
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function extractEnumProvider(): iterable
    {
        yield 'by value' => [true, false];
        yield 'by value, extracting enums' => [true, true];
        yield 'by reference' => [false, false];
        yield 'by reference, extracting enums' => [false, true];
    }

    #[DataProvider('extractEnumProvider')]
    public function testExtractEnum(bool $byValue, bool $extractEnums): void
    {
        $entity = new SimpleEntityWithEnum();
        $entity->setId(1);
        $entity->setEnum(SimpleEnum::Two);

        $hydrator = new DoctrineObject($this->createEnumObjectManager(), $byValue, typeConverter: new DoctrineTypeConverter($extractEnums));

        $this->assertSame(['id' => 1, 'enum' => $extractEnums ? 2 : SimpleEnum::Two], $hydrator->extract($entity));
    }

    private function createEnumObjectManager(): ObjectManager
    {
        return $this->createObjectManager([
            $this->createMetadata(
                SimpleEntityWithEnum::class,
                ['id' => 'integer', 'enum' => 'integer'],
                enumTypes: ['enum' => SimpleEnum::class],
            ),
        ]);
    }

    private function createEnumHydrator(bool $byValue): DoctrineObject
    {
        $metadata = $this->createMetadata(SimpleEntityWithEnum::class, ['id' => 'integer', 'enum' => 'integer']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);
        $hydrator->addStrategy('enum', new SimpleEnumStrategy());

        return $hydrator;
    }
}
