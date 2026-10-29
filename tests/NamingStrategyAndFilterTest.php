<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Laminas\Hydrator\Filter\PropertyName;
use DoctrineTest\Laminas\Hydrator\Assets\ByValueDifferentiatorEntity;
use DoctrineTest\Laminas\Hydrator\Assets\NamingStrategyEntity;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DoctrineObject::class)]
class NamingStrategyAndFilterTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testExtractUsesNamingStrategy(bool $byValue): void
    {
        $hydrator = $this->createNamingStrategyHydrator($byValue);

        $this->assertSame(['camel_case' => 'Foo'], $hydrator->extract(new NamingStrategyEntity('Foo')));
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateUsesNamingStrategy(bool $byValue): void
    {
        $hydrator = $this->createNamingStrategyHydrator($byValue);

        $entity = $hydrator->hydrate(['camel_case' => 'Bar'], new NamingStrategyEntity());

        $this->assertSame('Bar', $entity->getCamelCase());
    }

    #[DataProvider('byValueProvider')]
    public function testExtractWithPropertyNameFilter(bool $byValue): void
    {
        $entity = new ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $hydrator = $this->createHydrator($this->createObjectManager([$this->createByValueDifferentiatorMetadata()]), $byValue);
        $hydrator->addFilter('propertyname', new PropertyName(['id'], false));

        $this->assertSame(['id' => 2], $hydrator->extract($entity), 'Only the "id" field should have been extracted.');
    }

    private function createNamingStrategyHydrator(bool $byValue): DoctrineObject
    {
        $metadata = $this->createMetadata(NamingStrategyEntity::class, ['camelCase' => 'string'], identifier: ['camelCase']);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);
        $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());

        return $hydrator;
    }
}
