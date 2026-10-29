<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator;

use Closure;
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
use Laminas\Hydrator\NamingStrategy\NamingStrategyInterface;
use Laminas\Stdlib\ArrayUtils;
use LogicException;
use RuntimeException;
use Traversable;
use UnexpectedValueException;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function assert;
use function count;
use function ctype_upper;
use function current;
use function get_class_methods;
use function get_debug_type;
use function gettype;
use function in_array;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_object;
use function property_exists;
use function spl_object_id;
use function sprintf;
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

    protected TypeConverterInterface $typeConverter;

    protected AssociationExtraction $associationExtraction = AssociationExtraction::Object;

    protected int $associationExtractionMaxDepth = 1;

    /** @var array<class-string, DoctrineObject> Hydrators for associated entities by class, kept by the root hydrator */
    private array $associationHydrators = [];

    /** The hydrator configuring this hydrator as association hydrator, null for a root hydrator */
    private DoctrineObject|null $root = null;

    /** Whether this hydrator has been created automatically as association hydrator */
    private bool $inheritsSettings = false;

    /** Whether a naming strategy has been set or removed explicitly, otherwise it is inherited from the root */
    private bool $hasOwnNamingStrategy = false;

    /**
     * State of the current extraction, shared by the hydrators of all extracted entities
     *
     * @var array{level: int, path: array<int, true>, mode: AssociationExtraction, maxDepth: int}|null
     */
    private array|null $extractionContext = null;

    /**
     * @param ObjectManager               $objectManager The ObjectManager to use
     * @param bool                        $byValue       If set to true, hydrator will always use entity's public API
     * @param TypeConverterInterface|null $typeConverter Converts values of mapped fields, defaults to DoctrineTypeConverter
     * @param Closure|null                $instantiator  Creates new associated entities from their class name and data,
     *                                                   defaults to calling the constructor without arguments
     * @phpstan-param (Closure(class-string, array<array-key, mixed>): object)|null $instantiator
     */
    public function __construct(
        protected ObjectManager $objectManager,
        protected bool $byValue = true,
        Inflector|null $inflector = null,
        TypeConverterInterface|null $typeConverter = null,
        protected Closure|null $instantiator = null,
    ) {
        $this->inflector     = $inflector ?? InflectorFactory::create()->build();
        $this->typeConverter = $typeConverter ?? new DoctrineTypeConverter();
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

    public function getAssociationExtraction(): AssociationExtraction
    {
        return $this->associationExtraction;
    }

    public function getAssociationExtractionMaxDepth(): int
    {
        return $this->associationExtractionMaxDepth;
    }

    /**
     * Define how associations are represented when extracting an object. The setting of the hydrator
     * extracting the object applies to the associated entities at all levels.
     *
     * @param int $maxDepth Number of association levels extracted into arrays, only used
     *                      with AssociationExtraction::Array
     *
     * @throws InvalidArgumentException
     */
    public function setAssociationExtraction(AssociationExtraction $associationExtraction, int $maxDepth = 1): void
    {
        if ($maxDepth < 1) {
            throw new InvalidArgumentException(
                sprintf('The maximum depth for association extraction must be at least 1, %d given', $maxDepth),
            );
        }

        $this->associationExtraction         = $associationExtraction;
        $this->associationExtractionMaxDepth = $maxDepth;
    }

    /**
     * Returns the hydrator used for associated entities of the given class, at any level. Unless set
     * explicitly, it is created with the settings of this hydrator, but without its strategies and filters.
     *
     * @param class-string $className
     */
    public function getAssociationHydrator(string $className): DoctrineObject
    {
        return $this->getRoot()->resolveAssociationHydrator($className);
    }

    /**
     * Sets the hydrator used for associated entities of the given class, at any level
     *
     * @param class-string $className
     *
     * @throws InvalidArgumentException
     */
    public function setAssociationHydrator(string $className, DoctrineObject $hydrator): void
    {
        $root = $this->getRoot();
        if ($hydrator === $root || $hydrator->associationHydrators !== []) {
            throw new InvalidArgumentException(
                'An association hydrator must not have association hydrators itself, configure them on the root hydrator',
            );
        }

        $hydrator->root                         = $root;
        $hydrator->inheritsSettings             = false;
        $root->associationHydrators[$className] = $hydrator;
    }

    public function setNamingStrategy(NamingStrategyInterface $strategy): void
    {
        parent::setNamingStrategy($strategy);

        $this->hasOwnNamingStrategy = true;
    }

    public function removeNamingStrategy(): void
    {
        parent::removeNamingStrategy();

        $this->hasOwnNamingStrategy = true;
    }

    private function getRoot(): DoctrineObject
    {
        return $this->root ?? $this;
    }

    /**
     * Returns the association hydrator for a class, synchronizing the settings it inherits. Called on the root hydrator.
     *
     * @param class-string $className
     */
    private function resolveAssociationHydrator(string $className): DoctrineObject
    {
        if (! isset($this->associationHydrators[$className])) {
            $hydrator                   = new self($this->objectManager, $this->byValue, $this->inflector, $this->typeConverter, $this->instantiator);
            $hydrator->root             = $this;
            $hydrator->inheritsSettings = true;

            $this->associationHydrators[$className] = $hydrator;
        }

        $hydrator = $this->associationHydrators[$className];

        if ($hydrator->inheritsSettings) {
            $hydrator->defaultByValueStrategy     = $this->defaultByValueStrategy;
            $hydrator->defaultByReferenceStrategy = $this->defaultByReferenceStrategy;
        }

        if (! $hydrator->hasOwnNamingStrategy) {
            $hydrator->namingStrategy = $this->hasNamingStrategy() ? $this->namingStrategy : null;
        }

        return $hydrator;
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
        return $this->extractWithContext($object, [
            'level' => 0,
            'path' => [spl_object_id($object) => true],
            'mode' => $this->associationExtraction,
            'maxDepth' => $this->associationExtractionMaxDepth,
        ]);
    }

    /**
     * @param array{level: int, path: array<int, true>, mode: AssociationExtraction, maxDepth: int} $context
     *
     * @return array<array-key,mixed>
     */
    private function extractWithContext(object $object, array $context): array
    {
        // The hydrator may be used recursively for entities of the same class
        $previousMetadata        = $this->metadata;
        $previousContext         = $this->extractionContext;
        $this->extractionContext = $context;

        try {
            $this->prepare($object);

            return $this->byValue ? $this->extractByValue($object) : $this->extractByReference($object);
        } finally {
            $this->extractionContext = $previousContext;
            $this->metadata          = $previousMetadata ?? $this->metadata;
        }
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * {@inheritDoc}
     */
    public function hydrate(array $data, object $object): object
    {
        // The hydrator may be used recursively for entities of the same class
        $previousMetadata = $this->metadata;

        try {
            $this->prepare($object);

            return $this->byValue ? $this->hydrateByValue($data, $object) : $this->hydrateByReference($data, $object);
        } finally {
            $this->metadata = $previousMetadata ?? $this->metadata;
        }
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
                $data[$dataFieldName] = $this->extractAssociation($fieldName, $this->extractValue($fieldName, $object->$getter(), $object));
            } elseif (in_array($isser, $methods)) {
                $data[$dataFieldName] = $this->extractAssociation($fieldName, $this->extractValue($fieldName, $object->$isser(), $object));
            } elseif (
                str_starts_with($fieldName, 'is')
                && ctype_upper(substr($fieldName, 2, 1))
                && in_array($fieldName, $methods)
            ) {
                $data[$dataFieldName] = $this->extractAssociation($fieldName, $this->extractValue($fieldName, $object->$fieldName(), $object));
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
        if ($refl->isReadOnly()) {
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

            if ($reflProperty->isReadOnly()) {
                continue;
            }

            // skip uninitialized properties (available from PHP 7.4)
            if (! $reflProperty->isInitialized($object)) {
                continue;
            }

            $dataFieldName        = $this->computeExtractFieldName($fieldName);
            $data[$dataFieldName] = $this->extractAssociation(
                $fieldName,
                $this->extractValue($fieldName, $reflProperty->getValue($object), $object),
            );
        }

        return $data;
    }

    /**
     * Converts associated entities of an extracted value according to the configured
     * association extraction. Values that are not instances of the association's target
     * class, e.g. because a strategy already converted them, are left untouched.
     */
    private function extractAssociation(string $fieldName, mixed $value): mixed
    {
        if ($this->extractionContext === null || $this->extractionContext['mode'] === AssociationExtraction::Object) {
            return $value;
        }

        $metadata = $this->getClassMetadata();
        if (! $metadata->hasAssociation($fieldName)) {
            return $value;
        }

        $target = $metadata->getAssociationTargetClass($fieldName);
        if ($target === null) {
            return $value;
        }

        if ($metadata->isSingleValuedAssociation($fieldName)) {
            return $value instanceof $target ? $this->extractAssociatedObject($value, $target) : $value;
        }

        if (! is_iterable($value)) {
            return $value;
        }

        $values = [];
        foreach ($value as $key => $item) {
            $values[$key] = $item instanceof $target ? $this->extractAssociatedObject($item, $target) : $item;
        }

        return $values;
    }

    /** @param class-string $target */
    private function extractAssociatedObject(object $object, string $target): mixed
    {
        $objectId = spl_object_id($object);
        $context  = $this->extractionContext;
        assert($context !== null);

        if (
            $context['mode'] === AssociationExtraction::Array
            && $context['level'] < $context['maxDepth']
            && ! isset($context['path'][$objectId])
        ) {
            return $this->getRoot()->resolveAssociationHydrator($target)->extractWithContext($object, [
                'level' => $context['level'] + 1,
                'path' => $context['path'] + [$objectId => true],
            ] + $context);
        }

        $metadata         = $this->objectManager->getClassMetadata($target);
        $identifierValues = $metadata->getIdentifierValues($object);

        if (count($metadata->getIdentifierFieldNames()) === 1) {
            return $identifierValues === [] ? null : current($identifierValues);
        }

        return $identifierValues;
    }

    /**
     * Converts a value for extraction
     * Apply strategies first, then the type conversions
     */
    public function extractValue(string $name, mixed $value, object|null $object = null): mixed
    {
        $value = parent::extractValue($name, $value, $object);

        return $this->typeConverter->extract($value, $this->getClassMetadata(), $name);
    }

    /**
     * Converts a value for hydration
     * Apply strategies first, then the type conversions
     *
     * @param string                       $name  The name of the strategy to use.
     * @param mixed                        $value The value that should be converted.
     * @param array<array-key, mixed>|null $data  The whole data is optionally provided as context.
     */
    public function hydrateValue(string $name, mixed $value, array|null $data = null): mixed
    {
        $value = parent::hydrateValue($name, $value, $data);

        return $this->typeConverter->hydrate($value, $this->getClassMetadata(), $name);
    }

    /**
     * Hydrate the object using a by-value logic (this means that it uses the entity API, in this
     * case, setters)
     *
     * Caution: Parameter $object should not be null, signature will be changed to not-nullable in next major
     *
     * @param array<string,mixed> $data
     * @phpstan-param T $object
     *
     * @phpstan-return T
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
     * @phpstan-param T $object
     *
     * @phpstan-return T
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
            if ($reflProperty->isReadOnly()) {
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

                    // Like by value, null is ignored if the property does not accept it
                    if ($value === null && ! ($reflProperty->getType()?->allowsNull() ?? true)) {
                        continue;
                    }

                    $reflProperty->setValue($object, $value);
                } elseif ($metadata->isCollectionValuedAssociation($field)) {
                    $this->toMany($object, $field, $target, $value);
                }
            } else {
                $reflProperty->setValue($object, $this->hydrateValue($field, $value, $data));
            }
        }

        return $object;
    }

    /**
     * This function tries, given an array of data, to convert it to an object if the given array contains
     * an identifier for the object. This is useful in a context of updating existing entities, without ugly
     * tricks like setting manually the existing id directly into the entity
     *
     * @param array<string,mixed> $data The data that may contain identifiers keys
     * @phpstan-param T $object
     *
     * @phpstan-return T|null
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
            $object      = $this->find($identifiers, $target) ?: $this->instantiate($target, $value);

            return $this->getRoot()->resolveAssociationHydrator($target)->hydrate($value, $object);
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
                    $collection[] = is_array($value)
                        ? $this->getRoot()->resolveAssociationHydrator($target)->hydrate($value, $found)
                        : $found;
                    continue;
                }
            }

            $collection[] = is_array($value)
                ? $this->getRoot()->resolveAssociationHydrator($target)->hydrate($value, $this->instantiate($target, $value))
                : $this->instantiate($target, []);
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
     * Creates a new associated entity, using the instantiator if one is configured
     *
     * @param class-string<T>         $className
     * @param array<array-key, mixed> $data      The data the entity will be hydrated with
     *
     * @return T
     *
     * @throws UnexpectedValueException
     *
     * @template T of object
     */
    private function instantiate(string $className, array $data): object
    {
        if ($this->instantiator === null) {
            return new $className();
        }

        $object = ($this->instantiator)($className, $data);
        if (! $object instanceof $className) {
            throw new UnexpectedValueException(sprintf(
                'The instantiator must return an instance of "%s", "%s" returned',
                $className,
                get_debug_type($object),
            ));
        }

        return $object;
    }

    /**
     * Find an object by a given target class and identifier
     *
     * @phpstan-param class-string<T> $targetClass
     *
     * @phpstan-return T|null
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
