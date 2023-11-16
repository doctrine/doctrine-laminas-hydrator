<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator\Strategy;

use Doctrine\Common\Collections\ArrayCollection;
use LogicException;

use function array_udiff;
use function method_exists;
use function sprintf;

/**
 * When this strategy is used for Collections, if the new collection does not contain elements that are present in
 * the original collection, then this strategy will not remove those elements. At most, it will add new elements. For
 * instance, if the collection initially contains elements A and B, and that the new collection contains elements B
 * and C, then the final collection will contain elements A, B and C.
 * This strategy is by value, this means it will use the public API (in this case, remover)
 */
final class DisallowRemoveByValue extends AbstractCollectionStrategy
{
    /**
     * Converts the given value so that it can be hydrated by the hydrator.
     *
     * @param mixed                        $value The original value.
     * @param array<array-key, mixed>|null $data  The original data for context.
     *
     * @return mixed Returns the value that should be hydrated.
     */
    public function hydrate($value, array|null $data)
    {
        // AllowRemove strategy need "adder"
        $adder  = 'add' . $this->getInflector()->classify($this->getCollectionName());
        $object = $this->getObject();

        if (! method_exists($object, $adder)) {
            throw new LogicException(
                sprintf(
                    'DisallowRemove strategy for DoctrineModule hydrator requires %s to
                     be defined in %s entity domain code, but it seems to be missing',
                    $adder,
                    $object::class,
                ),
            );
        }

        $collection = $this->getCollectionFromObjectByValue();
        $collection = $collection->toArray();

        $toAdd = new ArrayCollection(array_udiff($value, $collection, [$this, 'compareObjects']));

        $object->$adder($toAdd);

        return $collection;
    }
}
