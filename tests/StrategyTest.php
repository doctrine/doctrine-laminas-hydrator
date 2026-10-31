<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use DoctrineTest\Laminas\Hydrator\Assets\ByValueDifferentiatorEntity;
use DoctrineTest\Laminas\Hydrator\Assets\ContextStrategy;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntity;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleStrategy;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use Laminas\Hydrator\Strategy\StrategyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function explode;
use function implode;

#[CoversClass(DoctrineObject::class)]
class StrategyTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testUsesStrategyWhenHydrating(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createObjectManager([$this->createByValueDifferentiatorMetadata()]), $byValue);
        $hydrator->addStrategy('field', new SimpleStrategy());

        $entity = $hydrator->hydrate(['field' => 'foo'], new ByValueDifferentiatorEntity());

        $this->assertSame(
            $byValue ? 'From setter: modified while hydrating' : 'modified while hydrating',
            $entity->getField(false),
        );
    }

    #[DataProvider('byValueProvider')]
    public function testUsesStrategyWhenExtracting(bool $byValue): void
    {
        $entity = new ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $hydrator = $this->createHydrator($this->createObjectManager([$this->createByValueDifferentiatorMetadata()]), $byValue);
        $hydrator->addStrategy('field', new SimpleStrategy());

        $this->assertSame(['id' => 2, 'field' => 'modified while extracting'], $hydrator->extract($entity));
    }

    /**
     * https://github.com/doctrine/DoctrineModule/issues/639
     */
    #[DataProvider('byValueProvider')]
    public function testStrategyCanHydrateArrayValues(bool $byValue): void
    {
        $metadata = $this->createMetadata(SimpleEntity::class, ['id' => 'integer', 'field' => 'string']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);
        $hydrator->addStrategy('field', new class implements StrategyInterface {
            /** @return string[] */
            public function extract(mixed $value, object|null $object = null): array
            {
                return explode(',', $value);
            }

            /** @param string[] $data */
            public function hydrate(mixed $value, array|null $data = null): string
            {
                return implode(',', $value);
            }
        });

        $entity = $hydrator->hydrate(['field' => ['complex', 'value']], new SimpleEntity());

        $this->assertSame('complex,value', $entity->getField());
    }

    #[DataProvider('byValueProvider')]
    public function testObjectIsPassedAsContextToStrategies(bool $byValue): void
    {
        $entity = new SimpleEntity();
        $entity->setId(2);
        $entity->setField('foo');

        $metadata = $this->createMetadata(SimpleEntity::class, ['id' => 'string', 'field' => 'string']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        $entity = $hydrator->hydrate(['id' => 3, 'field' => 'bar'], $entity);
        $this->assertSame(['id' => '3', 'field' => 'bar'], $hydrator->extract($entity));

        // The strategy appends the field when hydrating (from the data) and when extracting (from the object)
        $hydrator->addStrategy('id', new ContextStrategy());
        $entity = $hydrator->hydrate(['id' => '3', 'field' => 'bar'], $entity);

        $this->assertSame('3bar', $entity->getId());
        $this->assertSame(['id' => '3barbar', 'field' => 'bar'], $hydrator->extract($entity));
    }
}
