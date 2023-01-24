<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Laminas\Hydrator\Strategy\AbstractCollectionStrategy;
use LogicException;

use function array_udiff;
use function method_exists;
use function sprintf;

class DifferentAllowRemoveByValue extends AbstractCollectionStrategy
{
    /**
     * @param mixed                        $value
     * @param array<array-key, mixed>|null $data
     *
     * @return array|mixed|mixed[]
     */
    public function hydrate($value, ?array $data)
    {
        // AllowRemove strategy need "adder" and "remover"
        $adder   = 'add' . $this->getInflector()->classify($this->getCollectionName());
        $remover = 'remove' . $this->getInflector()->classify($this->getCollectionName());
        $object  = $this->getObject();

        if (! method_exists($object, $adder) || ! method_exists($object, $remover)) {
            throw new LogicException(
                sprintf(
                    'AllowRemove strategy for DoctrineModule hydrator requires both %s and %s to be defined in %s
                     entity domain code, but one or both seem to be missing',
                    $adder,
                    $remover,
                    $object::class
                )
            );
        }

        $collection = $this->getCollectionFromObjectByValue();
        $collection = $collection->toArray();

        $toAdd    = new ArrayCollection(array_udiff($value, $collection, [$this, 'compareObjects']));
        $toRemove = new ArrayCollection(array_udiff($collection, $value, [$this, 'compareObjects']));

        $object->$adder($toAdd);
        $object->$remover($toRemove);

        return $collection;
    }
}
