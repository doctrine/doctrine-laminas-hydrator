<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Laminas\Hydrator\TypeConverterInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;

use function get_debug_type;
use function is_scalar;
use function sprintf;

/**
 * Converts values into strings describing the conversion, to make the use of the converter visible
 */
final class PrefixingTypeConverter implements TypeConverterInterface
{
    public function hydrate(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
    {
        return sprintf('hydrated %s: %s', $fieldName, is_scalar($value) ? $value : get_debug_type($value));
    }

    public function extract(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
    {
        return sprintf('extracted %s: %s', $fieldName, is_scalar($value) ? $value : get_debug_type($value));
    }
}
