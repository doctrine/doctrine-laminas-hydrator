<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\EmbeddableEntity;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithEmbeddable;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DoctrineObject::class)]
class EmbeddableTest extends HydratorTestCase
{
    #[DataProvider('byValueProvider')]
    public function testExtractEmbeddable(bool $byValue): void
    {
        $entity = new SimpleEntityWithEmbeddable();
        $entity->setId(2);
        $entity->getEmbedded()->setField('foo');

        $data = $this->createHydrator($this->createEmbeddableObjectManager(), $byValue)->extract($entity);

        $this->assertSame(['id' => 2, 'embedded' => $entity->getEmbedded()], $data);
    }

    #[DataProvider('byValueProvider')]
    public function testHydrateEmbeddable(bool $byValue): void
    {
        $embedded = new EmbeddableEntity();
        $embedded->setField('foo');

        $entity = $this->createHydrator($this->createEmbeddableObjectManager(), $byValue)
            ->hydrate(['embedded' => $embedded], new SimpleEntityWithEmbeddable());

        $this->assertSame($embedded, $entity->getEmbedded());
    }

    private function createEmbeddableObjectManager(): ObjectManager
    {
        // Doctrine lists the fields of embeddables prefixed with the name of the embedded property
        return $this->createObjectManager([
            $this->createMetadata(SimpleEntityWithEmbeddable::class, ['id' => 'integer', 'embedded.field' => 'string']),
        ]);
    }
}
