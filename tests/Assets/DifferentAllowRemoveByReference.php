<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Common\Collections\Collection;
use Doctrine\Laminas\Hydrator\Strategy\AbstractCollectionStrategy;
use ReflectionException;

use function array_udiff;

class DifferentAllowRemoveByReference extends AbstractCollectionStrategy
{
    /**
     * @param mixed                        $value
     * @param array<array-key, mixed>|null $data
     *
     * @return Collection|mixed
     *
     * @throws ReflectionException
     */
    public function hydrate($value, ?array $data)
    {
        $collection      = $this->getCollectionFromObjectByReference();
        $collectionArray = $collection->toArray();

        $toAdd    = array_udiff($value, $collectionArray, [$this, 'compareObjects']);
        $toRemove = array_udiff($collectionArray, $value, [$this, 'compareObjects']);

        foreach ($toAdd as $element) {
            $collection->add($element);
        }

        foreach ($toRemove as $element) {
            $collection->removeElement($element);
        }

        return $collection;
    }
}
