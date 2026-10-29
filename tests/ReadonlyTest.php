<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use DoctrineTest\Laminas\Hydrator\Assets\ReadonlyEntity;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithReadonlyProperty;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

#[CoversClass(DoctrineObject::class)]
class ReadonlyTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testExtractReadonlyProperty(bool $byValue): void
    {
        $entity = new SimpleEntityWithReadonlyProperty(2);
        $entity->setField('value');

        $metadata = $this->createMetadata(SimpleEntityWithReadonlyProperty::class, ['id' => 'integer', 'field' => 'string']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        // By value, the readonly property is extracted through its getter, by reference it is skipped
        $this->assertSame(
            $byValue ? ['id' => 2, 'field' => 'value'] : ['field' => 'value'],
            $hydrator->extract($entity),
        );
    }

    #[DataProvider('byValueProvider')]
    public function testExtractReadonlyClass(bool $byValue): void
    {
        $metadata = $this->createMetadata(ReadonlyEntity::class, ['id' => 'integer', 'field' => 'string']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        if (! $byValue) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage(sprintf(
                'this class "%s" is readonly, data can\'t be extracted',
                ReadonlyEntity::class,
            ));
        }

        $this->assertSame(['id' => 2, 'field' => null], $hydrator->extract(new ReadonlyEntity(2, null)));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateReadonlyProperty(bool $byValue): void
    {
        $metadata = $this->createMetadata(SimpleEntityWithReadonlyProperty::class, ['id' => 'integer', 'field' => 'string']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        if (! $byValue) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage(sprintf(
                'Cannot hydrate class "%s" by reference. Property "id" is readonly. To fix this error, remove readonly',
                SimpleEntityWithReadonlyProperty::class,
            ));
        }

        // By value, the readonly property is skipped as it has no setter
        $entity = $hydrator->hydrate(['field' => 'toto', 'id' => 15], new SimpleEntityWithReadonlyProperty(3));

        $this->assertSame(3, $entity->getId());
        $this->assertSame('toto', $entity->getField());
    }
}
