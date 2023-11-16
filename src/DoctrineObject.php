<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByReference;
use Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByValue;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Generator;
use InvalidArgumentException;
use Laminas\Hydrator\AbstractHydrator;
use Laminas\Hydrator\Filter\FilterProviderInterface;
use Laminas\Stdlib\ArrayUtils;
use LogicException;
use RuntimeException;
use Traversable;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function assert;
use function ctype_upper;
use function current;
use function get_class_methods;
use function gettype;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_iterable;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;

class DoctrineObject extends AbstractHydrator
{
    protected ClassMetadata|null $metadata = null;

    /** @var class-string<Strategy\CollectionStrategyInterface> */
    protected string $defaultByValueStrategy = AllowRemoveByValue::class;

    /** @var class-string<Strategy\CollectionStrategyInterface> */
    protected string $defaultByReferenceStrategy = AllowRemoveByReference::class;

    protected Inflector $inflector;

    /**
     * @param ObjectManager $objectManager The ObjectManager to use
     * @param bool          $byValue       If set to true, hydrator will always use entity's public API
     */
    public function __construct(protected ObjectManager $objectManager, protected bool $byValue = true, Inflector|null $inflector = null)
    {
        $this->inflector = $inflector ?? InflectorFactory::create()->build();
    }

    protected function getClassMetadata(): ClassMetadata
    {
        if ($this->metadata === null) {
            throw new LogicException('Class metadata is not set, call prepare().');
        }

        return $this->metadata;
    }

    /** @return class-string<Strategy\CollectionStrategyInterface> */
    public function getDefaultByValueStrategy(): string
    {
        return $this->defaultByValueStrategy;
    }

    /** @param class-string<Strategy\CollectionStrategyInterface> $defaultByValueStrategy */
    public function setDefaultByValueStrategy(string $defaultByValueStrategy): void
    {
        $this->defaultByValueStrategy = $defaultByValueStrategy;
    }

    /** @return class-string<Strategy\CollectionStrategyInterface> */
    public function getDefaultByReferenceStrategy(): string
    {
        return $this->defaultByReferenceStrategy;
    }

    /** @param class-string<Strategy\CollectionStrategyInterface> $defaultByReferenceStrategy */
    public function setDefaultByReferenceStrategy(string $defaultByReferenceStrategy): void
    {
        $this->defaultByReferenceStrategy = $defaultByReferenceStrategy;
    }

    /**
     * Get all field names, this includes direct field names, names of embeddables and
     * associations. By using a key-based generator, duplicates are effectively removed.
     *
     * @return Generator<string>
     */
    public function getFieldNames(): iterable
    {
        $fields = [...$this->getClassMetadata()->getFieldNames(), ...$this->getClassMetadata()->getAssociationNames()];

        foreach ($fields as $fieldName) {
            $pos = strpos($fieldName, '.');
            if ($pos !== false) {
                $fieldName = substr($fieldName, 0, $pos);
            }

            yield $fieldName;
        }
    }

    /**
     * Extract values from an object
     *
     * @return array<array-key,mixed>
     */
    public function extract(object $object): array
    {
        $this->prepare($object);

        if ($this->byValue) {
            return $this->extractByValue($object);
        }

        return $this->extractByReference($object);
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * {@inheritDoc}
     */
    public function hydrate(array $data, object $object): object
    {
        $this->prepare($object);

        if ($this->byValue) {
            return $this->hydrateByValue($data, $object);
        }

        return $this->hydrateByReference($data, $object);
    }

    /**
     * Prepare the hydrator by adding strategies to every collection valued associations
     */
    protected function prepare(object $object): void
    {
        $this->metadata = $this->objectManager->getClassMetadata($object::class);
        $this->prepareStrategies();
    }

    /**
     * Prepare strategies before the hydrator is used
     *
     * @throws InvalidArgumentException
     */
    protected function prepareStrategies(): void
    {
        $associations = $this->getClassMetadata()->getAssociationNames();

        foreach ($associations as $association) {
            if (! $this->getClassMetadata()->isCollectionValuedAssociation($association)) {
                continue;
            }

            // Add a strategy if the association has none set by user
            if (! $this->hasStrategy($association)) {
                if ($this->byValue) {
                    $strategyClassName = $this->getDefaultByValueStrategy();
                } else {
                    $strategyClassName = $this->getDefaultByReferenceStrategy();
                }

                $this->addStrategy($association, new $strategyClassName());
            }

            $strategy = $this->getStrategy($association);

            if (! $strategy instanceof Strategy\CollectionStrategyInterface) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Strategies used for collections valued associations must inherit from %s, %s given',
                        Strategy\CollectionStrategyInterface::class,
                        $strategy::class,
                    ),
                );
            }

            $strategy->setCollectionName($association);
            $strategy->setClassMetadata($this->getClassMetadata());
        }
    }

    /**
     * Extract values from an object using a by-value logic (this means that it uses the entity
     * API, in this case, getters)
     *
     * @return array<string,mixed>
     *
     * @throws RuntimeException
     */
    protected function extractByValue(object $object): array
    {
        $methods = get_class_methods($object);
        $filter  = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        $data = [];
        foreach ($this->getFieldNames() as $fieldName) {
            if ($filter && ! $filter->filter($fieldName)) {
                continue;
            }

            $getter = 'get' . $this->inflector->classify($fieldName);
            $isser  = 'is' . $this->inflector->classify($fieldName);

            $dataFieldName = $this->computeExtractFieldName($fieldName);
            if (in_array($getter, $methods)) {
                $data[$dataFieldName] = $this->extractValue($fieldName, $object->$getter(), $object);
            } elseif (in_array($isser, $methods)) {
                $data[$dataFieldName] = $this->extractValue($fieldName, $object->$isser(), $object);
            } elseif (
                str_starts_with($fieldName, 'is')
                && ctype_upper(substr($fieldName, 2, 1))
                && in_array($fieldName, $methods)
            ) {
                $data[$dataFieldName] = $this->extractValue($fieldName, $object->$fieldName(), $object);
            }

            // Unknown fields are ignored
        }

        return $data;
    }

    /**
     * Extract values from an object using a by-reference logic (this means that values are
     * directly fetched without using the public API of the entity, in this case, getters)
     *
     * @return array<string,mixed>
     */
    protected function extractByReference(object $object): array
    {
        $refl   = $this->getClassMetadata()->getReflectionClass();
        $filter = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        $data = [];

        // fail for readonly classes (PHP 8.2)
        if (method_exists($refl, 'isReadOnly') && $refl->isReadOnly()) {
            throw new LogicException(
                sprintf(
                    'this class "%s" is readonly, data can\'t be extracted',
                    $object::class,
                ),
            );
        }

        foreach ($this->getFieldNames() as $fieldName) {
            if ($filter && ! $filter->filter($fieldName)) {
                continue;
            }

            $reflProperty = $refl->getProperty($fieldName);
            $reflProperty->setAccessible(true);

            if (method_exists($reflProperty, 'isReadOnly') && $reflProperty->isReadOnly()) {
                continue;
            }

            // skip uninitialized properties (available from PHP 7.4)
            if (! $reflProperty->isInitialized($object)) {
                continue;
            }

            $dataFieldName        = $this->computeExtractFieldName($fieldName);
            $data[$dataFieldName] = $this->extractValue($fieldName, $reflProperty->getValue($object), $object);
        }

        return $data;
    }

    /**
     * Converts a value for hydration
     * Apply strategies first, then the type conversions
     *
     * @param string                       $name  The name of the strategy to use.
     * @param mixed                        $value The value that should be converted.
     * @param array<array-key, mixed>|null $data  The whole data is optionally provided as context.
     *
     * @return mixed|null
     */
    public function hydrateValue(string $name, $value, array|null $data = null)
    {
        $value = parent::hydrateValue($name, $value, $data);

        if ($value === null && $this->isNullable($name)) {
            return null;
        }

        // BackedEnum is available from PHP 8.1 on
        if ($value instanceof BackedEnum) {
            return $value;
        }

        return $this->handleTypeConversions($value, $this->getClassMetadata()->getTypeOfField($name));
    }

    /**
     * Hydrate the object using a by-value logic (this means that it uses the entity API, in this
     * case, setters)
     *
     * Caution: Parameter $object should not be null, signature will be changed to not-nullable in next major
     *
     * @param array<string,mixed> $data
     * @psalm-param T $object
     *
     * @psalm-return T
     *
     * @throws RuntimeException
     *
     * @template T of object
     */
    protected function hydrateByValue(array $data, object|null $object): object
    {
        $tryObject = $this->tryConvertArrayToObject($data, $object);
        $metadata  = $this->getClassMetadata();

        if (is_object($tryObject)) {
            $object = $tryObject;
        }

        foreach ($data as $field => $value) {
            $field  = $this->computeHydrateFieldName($field);
            $setter = 'set' . $this->inflector->classify($field);

            if ($metadata->hasAssociation($field)) {
                $target = $metadata->getAssociationTargetClass($field);
                assert($target !== null);

                if ($metadata->isSingleValuedAssociation($field)) {
                    if (! is_callable([$object, $setter])) {
                        continue;
                    }

                    $value = $this->toOne($target, $this->hydrateValue($field, $value, $data));

                    if (
                        $value === null
                        && ! current($metadata->getReflectionClass()->getMethod($setter)->getParameters())->allowsNull()
                    ) {
                        continue;
                    }

                    $object->$setter($value);
                } elseif ($metadata->isCollectionValuedAssociation($field)) {
                    $this->toMany($object, $field, $target, $value);
                }
            } else {
                if (! is_callable([$object, $setter])) {
                    continue;
                }

                $object->$setter($this->hydrateValue($field, $value, $data));
            }

            $this->metadata = $metadata;
        }

        return $object;
    }

    /**
     * Hydrate the object using a by-reference logic (this means that values are modified directly without
     * using the public API, in this case setters, and hence override any logic that could be done in those
     * setters)
     *
     * Caution: Parameter $object should not be null, signature will be changed to not-nullable in next major
     *
     * @param array<string,mixed> $data
     * @psalm-param T $object
     *
     * @psalm-return T
     *
     * @template T of object
     */
    protected function hydrateByReference(array $data, object|null $object): object
    {
        $tryObject = $this->tryConvertArrayToObject($data, $object);
        $metadata  = $this->getClassMetadata();
        $refl      = $metadata->getReflectionClass();

        if (is_object($tryObject)) {
            $object = $tryObject;
        }

        foreach ($data as $field => $value) {
            $field = $this->computeHydrateFieldName($field);

            // Ignore unknown fields
            if (! $refl->hasProperty($field)) {
                continue;
            }

            $reflProperty = $refl->getProperty($field);

            // fail for readonly property (PHP 8.1)
            if (method_exists($reflProperty, 'isReadOnly') && $reflProperty->isReadOnly()) {
                throw new LogicException(
                    sprintf(
                        'Cannot hydrate class "%s" by reference. Property "%s" is readonly. To fix this error, remove readonly.',
                        $object::class,
                        $field,
                    ),
                );
            }

            $reflProperty->setAccessible(true);

            if ($metadata->hasAssociation($field)) {
                $target = $metadata->getAssociationTargetClass($field);
                assert($target !== null);

                if ($metadata->isSingleValuedAssociation($field)) {
                    $value = $this->toOne($target, $this->hydrateValue($field, $value, $data));
                    $reflProperty->setValue($object, $value);
                } elseif ($metadata->isCollectionValuedAssociation($field)) {
                    $this->toMany($object, $field, $target, $value);
                }
            } else {
                $reflProperty->setValue($object, $this->hydrateValue($field, $value, $data));
            }

            $this->metadata = $metadata;
        }

        return $object;
    }

    /**
     * This function tries, given an array of data, to convert it to an object if the given array contains
     * an identifier for the object. This is useful in a context of updating existing entities, without ugly
     * tricks like setting manually the existing id directly into the entity
     *
     * @param array<string,mixed> $data The data that may contain identifiers keys
     * @psalm-param T $object
     *
     * @psalm-return T|null
     *
     * @template T of object
     */
    protected function tryConvertArrayToObject(array $data, object $object): object|null
    {
        $metadata         = $this->getClassMetadata();
        $identifierNames  = $metadata->getIdentifierFieldNames();
        $identifierValues = [];

        if (empty($identifierNames)) {
            return $object;
        }

        foreach ($identifierNames as $identifierName) {
            if (! isset($data[$identifierName])) {
                return $object;
            }

            $identifierValues[$identifierName] = $data[$identifierName];
        }

        /** @var class-string<T> $targetClass */
        $targetClass = $metadata->getName();

        return $this->find($identifierValues, $targetClass);
    }

    /**
     * Handle ToOne associations
     * When $value is an array but is not the $target's identifiers, $value is
     * most likely an array of fieldset data. The identifiers will be determined
     * and a target instance will be initialized and then hydrated. The hydrated
     * target will be returned.
     *
     * @param  class-string $target
     */
    protected function toOne(string $target, mixed $value): object|null
    {
        $metadata = $this->objectManager->getClassMetadata($target);

        if (is_array($value) && array_keys($value) !== $metadata->getIdentifier()) {
            // $value is most likely an array of fieldset data
            $identifiers = array_intersect_key(
                $value,
                array_flip($metadata->getIdentifier()),
            );
            $object      = $this->find($identifiers, $target) ?: new $target();

            return $this->hydrate($value, $object);
        }

        return $this->find($value, $target);
    }

    /**
     * Handle ToMany associations. In proper Doctrine design, Collections should not be swapped, so
     * collections are always handled by reference. Internally, every collection is handled using
     * strategies that implement CollectionStrategyInterface, and that add or remove elements but without
     * changing the collection of the object
     *
     * @param  class-string $target
     *
     * @throws InvalidArgumentException
     */
    protected function toMany(object $object, string $collectionName, string $target, mixed $values): void
    {
        $metadata   = $this->objectManager->getClassMetadata($target);
        $identifier = $metadata->getIdentifier();

        if (! is_array($values) && ! $values instanceof Traversable) {
            $values = (array) $values;
        } elseif ($values instanceof Traversable) {
            $values = ArrayUtils::iteratorToArray($values);
        }

        $collection = [];

        // If the collection contains identifiers, fetch the objects from database
        foreach ($values as $value) {
            if ($value instanceof $target) {
                // assumes modifications have already taken place in object
                $collection[] = $value;
                continue;
            }

            if (empty($value)) {
                // assumes no id and retrieves new $target
                $collection[] = $this->find($value, $target);
                continue;
            }

            $find = [];
            foreach ($identifier as $field) {
                switch (gettype($value)) {
                    case 'object':
                        $getter = 'get' . $this->inflector->classify($field);

                        if (is_callable([$value, $getter])) {
                            $find[$field] = $value->$getter();
                        } elseif (property_exists($value, $field)) {
                            $find[$field] = $value->$field;
                        }

                        break;
                    case 'array':
                        if (array_key_exists($field, $value) && $value[$field] !== null) {
                            $find[$field] = $value[$field];
                        }

                        break;
                    default:
                        $find[$field] = $value;
                        break;
                }
            }

            if (! empty($find)) {
                $found = $this->find($find, $target);
                if ($found) {
                    $collection[] = is_array($value) ? $this->hydrate($value, $found) : $found;
                    continue;
                }
            }

            $collection[] = is_array($value) ? $this->hydrate($value, new $target()) : new $target();
        }

        $collection = array_filter(
            $collection,
            static fn ($item) => $item !== null,
        );

        // Set the object so that the strategy can extract the Collection from it
        $collectionStrategy = $this->getStrategy($collectionName);
        assert($collectionStrategy instanceof Strategy\CollectionStrategyInterface);
        $collectionStrategy->setObject($object);

        // We could directly call hydrate method from the strategy, but if people want to override
        // hydrateValue function, they can do it and do their own stuff
        $this->hydrateValue($collectionName, $collection, $values);
    }

    /**
     * Handle various type conversions that should be supported natively by Doctrine (like DateTime)
     * See Documentation of Doctrine Mapping Types for defaults
     *
     * @link http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/basic-mapping.html#doctrine-mapping-types
     *
     * @return mixed|null
     */
    protected function handleTypeConversions(mixed $value, string|null $typeOfField)
    {
        if ($value === null) {
            return null;
        }

        switch ($typeOfField) {
            case 'boolean':
                $value = (bool) $value;
                break;
            case 'string':
            case 'text':
            case 'bigint':
            case 'decimal':
                $value = (string) $value;
                break;
            case 'integer':
            case 'smallint':
                $value = (int) $value;
                break;
            case 'float':
                $value = (float) $value;
                break;
            case 'datetimetz':
            case 'datetimetz_immutable':
            case 'datetime':
            case 'datetime_immutable':
            case 'time':
            case 'time_immutable':
            case 'date':
            case 'date_immutable':
                if ($value === '') {
                    return null;
                }

                $isImmutable = str_ends_with($typeOfField, 'immutable');

                // Psalm has troubles with nested conditions, therefore break this into two return statements.
                // See https://github.com/vimeo/psalm/issues/6683.
                if ($isImmutable && $value instanceof DateTimeImmutable) {
                    return $value;
                }

                if (! $isImmutable && $value instanceof DateTime) {
                    return $value;
                }

                if ($isImmutable && $value instanceof DateTime) {
                    return DateTimeImmutable::createFromMutable($value);
                }

                if (! $isImmutable && $value instanceof DateTimeImmutable) {
                    return DateTime::createFromImmutable($value);
                }

                if (is_int($value)) {
                    $dateTime = $isImmutable
                        ? new DateTimeImmutable()
                        : new DateTime();

                    return $dateTime->setTimestamp($value);
                }

                if (is_string($value)) {
                    return $isImmutable
                        ? new DateTimeImmutable($value)
                        : new DateTime($value);
                }

                break;
            default:
                break;
        }

        return $value;
    }

    /**
     * Find an object by a given target class and identifier
     *
     * @psalm-param class-string<T> $targetClass
     *
     * @psalm-return T|null
     *
     * @template T of object
     */
    protected function find(mixed $identifiers, string $targetClass): object|null
    {
        if ($identifiers instanceof $targetClass) {
            return $identifiers;
        }

        if ($this->isNullIdentifier($identifiers)) {
            return null;
        }

        return $this->objectManager->find($targetClass, $identifiers);
    }

    /**
     * Verifies if a provided identifier is to be considered null
     *
     * @param array<string|null>|mixed $identifier
     */
    private function isNullIdentifier($identifier): bool
    {
        if ($identifier === null) {
            return true;
        }

        if (is_iterable($identifier)) {
            $nonNullIdentifiers = array_filter(
                ArrayUtils::iteratorToArray($identifier),
                static fn ($value) => $value !== null,
            );

            return empty($nonNullIdentifiers);
        }

        return false;
    }

    /**
     * Check the field is nullable
     */
    private function isNullable(string $name): bool
    {
        $metadata = $this->getClassMetadata();

        //TODO: need update after updating isNullable method of Doctrine\ORM\Mapping\ClassMetadata
        if ($metadata->hasField($name)) {
            return method_exists($metadata, 'isNullable') && $metadata->isNullable($name);
        }

        if ($metadata->hasAssociation($name) && method_exists($metadata, 'getAssociationMapping')) {
            $mapping = $metadata->getAssociationMapping($name);

            return $mapping !== false && isset($mapping['nullable']) && $mapping['nullable'];
        }

        return false;
    }

    /**
     * Applies the naming strategy if there is one set
     */
    protected function computeHydrateFieldName(string $field): string
    {
        if ($this->hasNamingStrategy()) {
            $field = $this->getNamingStrategy()->hydrate($field);
        }

        return $field;
    }

    /**
     * Applies the naming strategy if there is one set
     */
    protected function computeExtractFieldName(string $field): string
    {
        if ($this->hasNamingStrategy()) {
            $field = $this->getNamingStrategy()->extract($field);
        }

        return $field;
    }
}
