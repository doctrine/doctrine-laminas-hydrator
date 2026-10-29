<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator;

/**
 * Defines how DoctrineObject represents embeddables in the data array, when extracting and hydrating
 */
enum EmbeddableExtraction
{
    /**
     * Embeddables are extracted as they are and hydrated from objects (default)
     */
    case Object;

    /**
     * Embeddables are extracted into nested arrays, e.g. 'address' => ['postalCode' => '48149'],
     * and hydrated from nested arrays as well as from objects
     */
    case Array;

    /**
     * The fields of embeddables are extracted into the data of the entity, with keys joining the name of the
     * embeddable and the name of the field by a separator, e.g. 'address__postalCode' => '48149'. They are
     * hydrated from such keys as well as from objects.
     */
    case Flattened;
}
