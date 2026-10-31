<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator;

/**
 * Defines how DoctrineObject::extract() represents associated entities
 */
enum AssociationExtraction
{
    /**
     * Associated entities and collections are returned as they are (default)
     */
    case Object;

    /**
     * Associated entities are replaced by their identifiers: a single value for simple
     * identifiers, an array for composite identifiers. Collections become arrays of identifiers.
     */
    case Identifier;

    /**
     * Associated entities are extracted recursively into arrays. Entities beyond the maximum
     * depth or already being extracted further up (circular references) are replaced by their
     * identifiers.
     */
    case Array;
}
