<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Laminas\Hydrator\Strategy\AbstractCollectionStrategy;
use LogicException;

use function array_udiff;
use function get_class;
use function method_exists;
use function sprintf;

class DifferentAllowRemoveByValue extends AbstractCollectionStrategy
{
    /**
     * @param mixed      $value
     * @param array|null $data
     *
     * @return array|mixed|mixed[]
     */
    public function hydrate($value, ?array $data)
    {
        // AllowRemove strategy need "adder" and "remover"
        $adder   = 'add' . $this->inflector->classify($this->collectionName);
        $remover = 'remove' . $this->inflector->classify($this->collectionName);

        if (! method_exists($this->object, $adder) || ! method_exists($this->object, $remover)) {
            throw new LogicException(
                sprintf(
                    'AllowRemove strategy for DoctrineModule hydrator requires both %s and %s to be defined in %s
                     entity domain code, but one or both seem to be missing',
                    $adder,
                    $remover,
                    get_class($this->object)
                )
            );
        }

        $collection = $this->getCollectionFromObjectByValue();
        $collection = $collection->toArray();

        $toAdd    = new ArrayCollection(array_udiff($value, $collection, [$this, 'compareObjects']));
        $toRemove = new ArrayCollection(array_udiff($collection, $value, [$this, 'compareObjects']));

        $this->object->$adder($toAdd);
        $this->object->$remover($toRemove);

        return $collection;
    }
}
