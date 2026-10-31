<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator;

use Doctrine\Persistence\Mapping\ClassMetadata;

/**
 * Converts values of mapped fields between the data array and the PHP type of the field
 */
interface TypeConverterInterface
{
    /**
     * Converts a value from the data array into the PHP type of the mapped field
     */
    public function hydrate(mixed $value, ClassMetadata $metadata, string $fieldName): mixed;

    /**
     * Converts the value of a mapped field for the data array
     */
    public function extract(mixed $value, ClassMetadata $metadata, string $fieldName): mixed;
}
