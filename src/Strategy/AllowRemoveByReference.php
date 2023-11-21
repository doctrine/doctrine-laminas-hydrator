<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator\Strategy;

use function array_udiff;

/**
 * When this strategy is used for Collections, if the new collection does not contain elements that are present in
 * the original collection, then this strategy remove elements from the original collection. For instance, if the
 * collection initially contains elements A and B, and that the new collection contains elements B and C, then the
 * final collection will contain elements B and C (while element A will be asked to be removed).
 * This strategy is by reference, this means it won't use public API to add/remove elements to the collection
 */
final class AllowRemoveByReference extends AbstractCollectionStrategy
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
