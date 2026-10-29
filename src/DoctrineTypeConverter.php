<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator;

use BackedEnum;
use BCMath\Number;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;
use ReflectionEnum;

use function class_exists;
use function get_debug_type;
use function in_array;
use function is_a;
use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;
use function str_ends_with;
use function substr;

/**
 * Converts values based on the Doctrine mapping types of the fields and the enum types of their mappings
 *
 * When hydrating, values are converted into the PHP type of the field. When extracting, values are
 * returned as they are, unless the conversion of enums or date and time values is enabled.
 *
 * Converters for further Doctrine types, e.g. custom DBAL types, can be registered by type name.
 */
final class DoctrineTypeConverter implements TypeConverterInterface
{
    /** Format of date intervals, as used by Doctrine DBAL */
    private const string DATE_INTERVAL_FORMAT = '%RP%YY%MM%DDT%HH%IM%SS';

    private const array DATE_TIME_TYPES = [
        'date',
        'date_immutable',
        'time',
        'time_immutable',
        'datetime',
        'datetime_immutable',
        'datetime_utc',
        'datetime_utc_immutable',
        'datetimetz',
        'datetimetz_immutable',
    ];

    /**
     * @param bool                                  $extractEnums     Extract backed enums as their backing value
     * @param bool                                  $extractDateTimes Extract date, time and date interval values as strings
     * @param array<string, TypeConverterInterface> $typeConverters   Converters by Doctrine type name, replacing the
     *                                                                built-in conversion of that type
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly bool $extractEnums = false,
        private readonly bool $extractDateTimes = false,
        private readonly array $typeConverters = [],
    ) {
        foreach ($typeConverters as $type => $typeConverter) {
            if (! $typeConverter instanceof TypeConverterInterface) {
                throw new InvalidArgumentException(sprintf(
                    'The converter for type "%s" must implement %s, %s given',
                    $type,
                    TypeConverterInterface::class,
                    get_debug_type($typeConverter),
                ));
            }
        }
    }

    public function hydrate(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
    {
        $typeConverter = $this->getTypeConverter($metadata, $fieldName);
        if ($typeConverter !== null) {
            return $typeConverter->hydrate($value, $metadata, $fieldName);
        }

        // Enums returned by a strategy are kept as they are
        if ($value === null || $value instanceof BackedEnum) {
            return $value;
        }

        $enumType = $this->getEnumType($metadata, $fieldName);
        if ($enumType !== null) {
            return $this->hydrateEnum($value, $enumType);
        }

        $type = $metadata->getTypeOfField($fieldName);

        return match (true) {
            $type === 'boolean' => (bool) $value,
            in_array($type, ['integer', 'smallint'], true) => (int) $value,
            in_array($type, ['float', 'smallfloat'], true) => (float) $value,
            in_array($type, ['string', 'ascii_string', 'text', 'guid', 'enum', 'bigint', 'decimal'], true) => (string) $value,
            $type === 'number' => $this->hydrateNumber($value),
            $type === 'dateinterval' => $this->hydrateDateInterval($value),
            in_array($type, self::DATE_TIME_TYPES, true) => $this->hydrateDateTime($value, str_ends_with($type, '_immutable')),
            default => $value,
        };
    }

    public function extract(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
    {
        $typeConverter = $this->getTypeConverter($metadata, $fieldName);
        if ($typeConverter !== null) {
            return $typeConverter->extract($value, $metadata, $fieldName);
        }

        if ($this->extractEnums && $value instanceof BackedEnum) {
            return $value->value;
        }

        if (! $this->extractDateTimes) {
            return $value;
        }

        if ($value instanceof DateInterval) {
            return $value->format(self::DATE_INTERVAL_FORMAT);
        }

        if ($value instanceof DateTimeInterface) {
            return match ($metadata->getTypeOfField($fieldName)) {
                'date', 'date_immutable' => $value->format('Y-m-d'),
                'time', 'time_immutable' => $value->format('H:i:s'),
                default => $value->format(DateTimeInterface::ATOM),
            };
        }

        return $value;
    }

    /**
     * Returns the converter registered for the type of a field. Fields mapped to an enum type
     * are always converted to enums.
     */
    private function getTypeConverter(ClassMetadata $metadata, string $fieldName): TypeConverterInterface|null
    {
        if ($this->typeConverters === []) {
            return null;
        }

        $type = $metadata->getTypeOfField($fieldName);
        if ($type === null || ! isset($this->typeConverters[$type]) || $this->getEnumType($metadata, $fieldName) !== null) {
            return null;
        }

        return $this->typeConverters[$type];
    }

    /**
     * Returns the enum type of a field. The enum type is not part of the persistence interface:
     * Doctrine ORM 3 returns a mapping object, ORM 2 and ODM return an array.
     *
     * @return class-string<BackedEnum>|null
     */
    private function getEnumType(ClassMetadata $metadata, string $fieldName): string|null
    {
        if (! method_exists($metadata, 'getFieldMapping') || ! $metadata->hasField($fieldName)) {
            return null;
        }

        $mapping  = $metadata->getFieldMapping($fieldName);
        $enumType = match (true) {
            is_array($mapping) => $mapping['enumType'] ?? null,
            is_object($mapping) => $mapping->enumType ?? null,
            default => null,
        };

        return is_string($enumType) && is_a($enumType, BackedEnum::class, true) ? $enumType : null;
    }

    /** @param class-string<BackedEnum> $enumType */
    private function hydrateEnum(mixed $value, string $enumType): BackedEnum
    {
        // Values from forms are strings, but enums backed by integers require integers
        if (is_string($value) && is_numeric($value) && (string) (new ReflectionEnum($enumType))->getBackingType() === 'int') {
            $value = (int) $value;
        }

        return $enumType::from($value);
    }

    private function hydrateNumber(mixed $value): mixed
    {
        // BCMath\Number is available as of PHP 8.4
        if (! class_exists(Number::class) || $value instanceof Number) {
            return $value;
        }

        if (is_float($value)) {
            $value = (string) $value;
        }

        return is_int($value) || is_string($value) ? new Number($value) : $value;
    }

    private function hydrateDateInterval(mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        // A leading sign is supported, as Doctrine DBAL stores negative intervals that way
        $negative = $value[0] === '-';
        if ($value[0] === '-' || $value[0] === '+') {
            $value = substr($value, 1);
        }

        $interval = new DateInterval($value);
        if ($negative) {
            $interval->invert = 1;
        }

        return $interval;
    }

    private function hydrateDateTime(mixed $value, bool $immutable): mixed
    {
        if ($value === '') {
            return null;
        }

        if ($immutable && $value instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        if (! $immutable && $value instanceof DateTimeImmutable) {
            return DateTime::createFromImmutable($value);
        }

        if (is_int($value)) {
            return ($immutable ? new DateTimeImmutable() : new DateTime())->setTimestamp($value);
        }

        if (is_string($value)) {
            return $immutable ? new DateTimeImmutable($value) : new DateTime($value);
        }

        return $value;
    }
}
