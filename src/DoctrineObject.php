<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator;

use DateTime;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByReference;
use Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByValue;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use InvalidArgumentException;
use Laminas\Hydrator\AbstractHydrator;
use Laminas\Hydrator\Filter\FilterProviderInterface;
use Laminas\Stdlib\ArrayUtils;
use RuntimeException;
use Traversable;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function ctype_upper;
use function current;
use function get_class;
use function get_class_methods;
use function gettype;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_string;
use function ltrim;
use function method_exists;
use function property_exists;
use function sprintf;
use function substr;

class DoctrineObject extends AbstractHydrator
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var ClassMetadata */
    protected $metadata;

    /** @var bool */
    protected $byValue = true;

    /** @var string */
    protected $defaultByValueStrategy = AllowRemoveByValue::class;

    /** @var string */
    protected $defaultByReferenceStrategy = AllowRemoveByReference::class;

    /** @var Inflector */
    private $inflector;

    /**
     * @param ObjectManager $objectManager The ObjectManager to use
     * @param bool          $byValue If set to true, hydrator will always use entity's public API
     */
    public function __construct(ObjectManager $objectManager, $byValue = true, ?Inflector $inflector = null)
    {
        $this->objectManager = $objectManager;
        $this->byValue       = (bool) $byValue;
        $this->inflector     = $inflector ?? InflectorFactory::create()->build();
    }

    /**
     * @return string
     */
    public function getDefaultByValueStrategy()
    {
        return $this->defaultByValueStrategy;
    }

    /**
     * @param string $defaultByValueStrategy
     * @return $this
     */
    public function setDefaultByValueStrategy($defaultByValueStrategy)
    {
        $this->defaultByValueStrategy = $defaultByValueStrategy;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultByReferenceStrategy()
    {
        return $this->defaultByReferenceStrategy;
    }

    /**
     * @param string $defaultByReferenceStrategy
     * @return $this
     */
    public function setDefaultByReferenceStrategy($defaultByReferenceStrategy)
    {
        $this->defaultByReferenceStrategy = $defaultByReferenceStrategy;
        return $this;
    }

    /**
     * Extract values from an object
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
     * @return object
     */
    public function hydrate(array $data, object $object)
    {
        $this->prepare($object);

        if ($this->byValue) {
            return $this->hydrateByValue($data, $object);
        }

        return $this->hydrateByReference($data, $object);
    }

    /**
     * Prepare the hydrator by adding strategies to every collection valued associations
     *
     * @param  object $object
     */
    protected function prepare($object)
    {
        $this->metadata = $this->objectManager->getClassMetadata(get_class($object));
        $this->prepareStrategies();
    }

    /**
     * Prepare strategies before the hydrator is used
     *
     * @throws InvalidArgumentException
     */
    protected function prepareStrategies()
    {
        $associations = $this->metadata->getAssociationNames();

        foreach ($associations as $association) {
            if ($this->metadata->isCollectionValuedAssociation($association)) {
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

                if (! $strategy instanceof Strategy\AbstractCollectionStrategy) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Strategies used for collections valued associations must inherit from '
                            . 'Strategy\AbstractCollectionStrategy, %s given',
                            get_class($strategy)
                        )
                    );
                }

                $strategy->setCollectionName($association)
                    ->setClassMetadata($this->metadata);
            }
        }
    }

    /**
     * Extract values from an object using a by-value logic (this means that it uses the entity
     * API, in this case, getters)
     *
     * @param  object $object
     * @throws RuntimeException
     * @return array
     */
    protected function extractByValue($object)
    {
        $fieldNames = array_merge($this->metadata->getFieldNames(), $this->metadata->getAssociationNames());
        $methods    = get_class_methods($object);
        $filter     = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        $data = [];
        foreach ($fieldNames as $fieldName) {
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
                substr($fieldName, 0, 2) === 'is'
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
     * @param  object $object
     * @return array
     */
    protected function extractByReference($object)
    {
        $fieldNames = array_merge($this->metadata->getFieldNames(), $this->metadata->getAssociationNames());
        $refl       = $this->metadata->getReflectionClass();
        $filter     = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        $data = [];
        foreach ($fieldNames as $fieldName) {
            if ($filter && ! $filter->filter($fieldName)) {
                continue;
            }
            $reflProperty = $refl->getProperty($fieldName);
            $reflProperty->setAccessible(true);

            // skip uninitialized properties (available from PHP 7.4)
            if (PHP_VERSION_ID < 70400 || $reflProperty->isInitialized($object)) {
                $dataFieldName = $this->computeExtractFieldName($fieldName);
                $data[$dataFieldName] = $this->extractValue($fieldName, $reflProperty->getValue($object), $object);
            }
        }

        return $data;
    }

    /**
     * Converts a value for hydration
     * Apply strategies first, then the type conversions
     *
     * @param  string     $name  The name of the strategy to use.
     * @param  mixed      $value The value that should be converted.
     * @param  null|array $data  The whole data is optionally provided as context.
     * @return mixed|null
     */
    public function hydrateValue(string $name, $value, ?array $data = null)
    {
        $value = parent::hydrateValue($name, $value, $data);

        if (null === $value && $this->isNullable($name)) {
            return null;
        }

        return $this->handleTypeConversions($value, $this->metadata->getTypeOfField($name));
    }

    /**
     * Hydrate the object using a by-value logic (this means that it uses the entity API, in this
     * case, setters)
     *
     * @param  object $object
     * @throws RuntimeException
     * @return object
     */
    protected function hydrateByValue(array $data, $object)
    {
        $tryObject = $this->tryConvertArrayToObject($data, $object);
        $metadata  = $this->metadata;

        if (is_object($tryObject)) {
            $object = $tryObject;
        }

        foreach ($data as $field => $value) {
            $field  = $this->computeHydrateFieldName($field);
            $setter = 'set' . $this->inflector->classify($field);

            if ($metadata->hasAssociation($field)) {
                $target = $metadata->getAssociationTargetClass($field);

                if ($metadata->isSingleValuedAssociation($field)) {
                    if (! is_callable([$object, $setter])) {
                        continue;
                    }

                    $value = $this->toOne($target, $this->hydrateValue($field, $value, $data));

                    if (
                        null === $value
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
     * @param  object $object
     * @return object
     */
    protected function hydrateByReference(array $data, $object)
    {
        $tryObject = $this->tryConvertArrayToObject($data, $object);
        $metadata  = $this->metadata;
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
            $reflProperty->setAccessible(true);

            if ($metadata->hasAssociation($field)) {
                $target = $metadata->getAssociationTargetClass($field);

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
     * @param  array  $data The data that may contain identifiers keys
     * @param  object $object
     * @return object
     */
    protected function tryConvertArrayToObject($data, $object)
    {
        $metadata         = $this->metadata;
        $identifierNames  = $metadata->getIdentifierFieldNames($object);
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

        return $this->find($identifierValues, $metadata->getName());
    }

    /**
     * Handle ToOne associations
     * When $value is an array but is not the $target's identifiers, $value is
     * most likely an array of fieldset data. The identifiers will be determined
     * and a target instance will be initialized and then hydrated. The hydrated
     * target will be returned.
     *
     * @param  string $target
     * @param  mixed  $value
     * @return object
     */
    protected function toOne($target, $value)
    {
        $metadata = $this->objectManager->getClassMetadata($target);

        if (is_array($value) && array_keys($value) !== $metadata->getIdentifier()) {
            // $value is most likely an array of fieldset data
            $identifiers = array_intersect_key(
                $value,
                array_flip($metadata->getIdentifier())
            );
            $object      = $this->find($identifiers, $target) ?: new $target();
            return $this->hydrate($value, $object);
        }

        return $this->find($value, $target);
    }

    /**
     * Handle ToMany associations. In proper Doctrine design, Collections should not be swapped, so
     * collections are always handled by reference. Internally, every collection is handled using specials
     * strategies that inherit from AbstractCollectionStrategy class, and that add or remove elements but without
     * changing the collection of the object
     *
     * @param  object $object
     * @param  mixed  $collectionName
     * @param  string $target
     * @param  mixed  $values
     * @throws InvalidArgumentException
     */
    protected function toMany($object, $collectionName, $target, $values)
    {
        $metadata   = $this->objectManager->getClassMetadata(ltrim($target, '\\'));
        $identifier = $metadata->getIdentifier();

        if (! is_array($values) && ! $values instanceof Traversable) {
            $values = (array) $values;
        }

        $collection = [];

        // If the collection contains identifiers, fetch the objects from database
        foreach ($values as $value) {
            if ($value instanceof $target) {
                // assumes modifications have already taken place in object
                $collection[] = $value;
                continue;
            } elseif (empty($value)) {
                // assumes no id and retrieves new $target
                $collection[] = $this->find($value, $target);
                continue;
            }

            $find = [];
            if (is_array($identifier)) {
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
                                unset($value[$field]); // removed identifier from persistable data
                            }
                            break;
                        default:
                            $find[$field] = $value;
                            break;
                    }
                }
            }

            if (! empty($find) && $found = $this->find($find, $target)) {
                $collection[] = is_array($value) ? $this->hydrate($value, $found) : $found;
            } else {
                $collection[] = is_array($value) ? $this->hydrate($value, new $target()) : new $target();
            }
        }

        $collection = array_filter(
            $collection,
            function ($item) {
                return null !== $item;
            }
        );

        // Set the object so that the strategy can extract the Collection from it

        /** @var Strategy\AbstractCollectionStrategy $collectionStrategy */
        $collectionStrategy = $this->getStrategy($collectionName);
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
     * @param  mixed  $value
     * @param  string $typeOfField
     * @return DateTime|null
     */
    protected function handleTypeConversions($value, $typeOfField)
    {
        if (null === $value) {
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
                $value = (double) $value;
                break;
            case 'datetimetz':
            case 'datetime':
            case 'time':
            case 'date':
                if ($value === '') {
                    return null;
                }

                if ($value instanceof DateTime) {
                    return $value;
                }

                if (is_int($value)) {
                    $dateTime = new DateTime();
                    $dateTime->setTimestamp($value);
                    return $dateTime;
                }

                if (is_string($value)) {
                    return new DateTime($value);
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
     * @param  mixed  $identifiers
     * @param  string $targetClass
     * @return object|null
     */
    protected function find($identifiers, $targetClass)
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
     * @param  mixed $identifier
     * @return bool
     */
    private function isNullIdentifier($identifier)
    {
        if (null === $identifier) {
            return true;
        }

        if ($identifier instanceof Traversable || is_array($identifier)) {
            $nonNullIdentifiers = array_filter(
                ArrayUtils::iteratorToArray($identifier),
                function ($value) {
                    return null !== $value;
                }
            );

            return empty($nonNullIdentifiers);
        }

        return false;
    }

    /**
     * Check the field is nullable
     *
     * @param  string $name
     * @return bool
     */
    private function isNullable($name)
    {
        //TODO: need update after updating isNullable method of Doctrine\ORM\Mapping\ClassMetadata
        if ($this->metadata->hasField($name)) {
            return method_exists($this->metadata, 'isNullable') && $this->metadata->isNullable($name);
        }

        if ($this->metadata->hasAssociation($name) && method_exists($this->metadata, 'getAssociationMapping')) {
            $mapping = $this->metadata->getAssociationMapping($name);

            return false !== $mapping && isset($mapping['nullable']) && $mapping['nullable'];
        }

        return false;
    }

    /**
     * Applies the naming strategy if there is one set
     *
     * @param string $field
     * @return string
     */
    protected function computeHydrateFieldName($field)
    {
        if ($this->hasNamingStrategy()) {
            $field = $this->getNamingStrategy()->hydrate($field);
        }
        return $field;
    }

    /**
     * Applies the naming strategy if there is one set
     *
     * @param string $field
     * @return string
     */
    protected function computeExtractFieldName($field)
    {
        if ($this->hasNamingStrategy()) {
            $field = $this->getNamingStrategy()->extract($field);
        }
        return $field;
    }
}
