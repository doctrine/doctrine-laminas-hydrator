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
use ReflectionNamedType;
use ReflectionProperty;
use Traversable;
use UnexpectedValueException;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_pop;
use function assert;
use function class_exists;
use function count;
use function ctype_upper;
use function current;
use function explode;
use function get_class_methods;
use function get_debug_type;
use function gettype;
use function implode;
use function in_array;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_object;
use function is_string;
use function property_exists;
use function spl_object_id;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strpos;
use function substr;

final class DoctrineObject extends AbstractHydrator
{
    private ClassMetadata|null $metadata = null;

    /** @var class-string<Strategy\CollectionStrategyInterface> */
    private string $defaultByValueStrategy = AllowRemoveByValue::class;

    /** @var class-string<Strategy\CollectionStrategyInterface> */
    private string $defaultByReferenceStrategy = AllowRemoveByReference::class;

    private Inflector $inflector;

    private TypeConverterInterface $typeConverter;

    private AssociationExtraction $associationExtraction = AssociationExtraction::Object;

    private int $associationExtractionMaxDepth = 1;

    private EmbeddableExtraction $embeddableExtraction = EmbeddableExtraction::Object;

    private string $embeddableSeparator = '__';

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
     * @var array{level: int, path: array<int, true>, mode: AssociationExtraction, maxDepth: int, embeddables: array{mode: EmbeddableExtraction, separator: string}}|null
     */
    private array|null $extractionContext = null;

    /**
     * Embeddable settings of the current extraction or hydration, shared by the hydrators of all entities
     *
     * @var array{mode: EmbeddableExtraction, separator: string}|null
     */
    private array|null $embeddableContext = null;

    /**
     * @param ObjectManager               $objectManager The ObjectManager to use
     * @param bool                        $byValue       If set to true, hydrator will always use entity's public API
     * @param TypeConverterInterface|null $typeConverter Converts values of mapped fields, defaults to DoctrineTypeConverter
     * @param Closure|null                $instantiator  Creates new associated entities from their class name and data,
     *                                                   defaults to calling the constructor without arguments
     * @phpstan-param (Closure(class-string, array<array-key, mixed>): object)|null $instantiator
     */
    public function __construct(
        private ObjectManager $objectManager,
        private bool $byValue = true,
        Inflector|null $inflector = null,
        TypeConverterInterface|null $typeConverter = null,
        private Closure|null $instantiator = null,
    ) {
        $this->inflector     = $inflector ?? InflectorFactory::create()->build();
        $this->typeConverter = $typeConverter ?? new DoctrineTypeConverter();
    }

    private function getClassMetadata(): ClassMetadata
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

    public function getEmbeddableExtraction(): EmbeddableExtraction
    {
        return $this->embeddableExtraction;
    }

    public function getEmbeddableSeparator(): string
    {
        return $this->embeddableSeparator;
    }

    /**
     * Define how embeddables are represented in the data, when extracting and hydrating. The setting of the
     * hydrator extracting or hydrating the object applies to the associated entities at all levels.
     *
     * @param string $separator Separator joining the names of embeddables and their fields, only used
     *                          with EmbeddableExtraction::Flattened
     *
     * @throws InvalidArgumentException
     */
    public function setEmbeddableExtraction(EmbeddableExtraction $embeddableExtraction, string $separator = '__'): void
    {
        if ($separator === '') {
            throw new InvalidArgumentException('The separator for flattened embeddables must not be empty');
        }

        $this->embeddableExtraction = $embeddableExtraction;
        $this->embeddableSeparator  = $separator;
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
     * Get all field names, this includes direct field names, names of embeddables and associations.
     * The fields of embeddables are listed by Doctrine as e.g. "address.postalCode", they are
     * returned once by the name of the embeddable.
     *
     * @return Generator<string>
     */
    private function getFieldNames(): iterable
    {
        $fields = [...$this->getClassMetadata()->getFieldNames(), ...$this->getClassMetadata()->getAssociationNames()];
        $seen   = [];

        foreach ($fields as $fieldName) {
            $pos = strpos($fieldName, '.');
            if ($pos !== false) {
                $fieldName = substr($fieldName, 0, $pos);
            }

            if (isset($seen[$fieldName])) {
                continue;
            }

            $seen[$fieldName] = true;

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
            'embeddables' => ['mode' => $this->embeddableExtraction, 'separator' => $this->embeddableSeparator],
        ]);
    }

    /**
     * @param array{level: int, path: array<int, true>, mode: AssociationExtraction, maxDepth: int, embeddables: array{mode: EmbeddableExtraction, separator: string}} $context
     *
     * @return array<array-key,mixed>
     */
    private function extractWithContext(object $object, array $context): array
    {
        // The hydrator may be used recursively for entities of the same class
        $previousMetadata          = $this->metadata;
        $previousContext           = $this->extractionContext;
        $previousEmbeddableContext = $this->embeddableContext;
        $this->extractionContext   = $context;
        $this->embeddableContext   = $context['embeddables'];

        try {
            $this->prepare($object);

            return $this->byValue ? $this->extractByValue($object) : $this->extractByReference($object);
        } finally {
            $this->extractionContext = $previousContext;
            $this->embeddableContext = $previousEmbeddableContext;
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
        return $this->hydrateWithContext($data, $object, [
            'mode' => $this->embeddableExtraction,
            'separator' => $this->embeddableSeparator,
        ]);
    }

    /**
     * @param array<array-key, mixed>                              $data
     * @param array{mode: EmbeddableExtraction, separator: string} $embeddableContext
     * @phpstan-param T $object
     *
     * @phpstan-return T
     *
     * @template T of object
     */
    private function hydrateWithContext(array $data, object $object, array $embeddableContext): object
    {
        // The hydrator may be used recursively for entities of the same class
        $previousMetadata          = $this->metadata;
        $previousEmbeddableContext = $this->embeddableContext;
        $this->embeddableContext   = $embeddableContext;

        try {
            $this->prepare($object);

            return $this->byValue ? $this->hydrateByValue($data, $object) : $this->hydrateByReference($data, $object);
        } finally {
            $this->embeddableContext = $previousEmbeddableContext;
            $this->metadata          = $previousMetadata ?? $this->metadata;
        }
    }

    /** @return array{mode: EmbeddableExtraction, separator: string} */
    private function getEmbeddableContext(): array
    {
        return $this->embeddableContext ?? ['mode' => $this->embeddableExtraction, 'separator' => $this->embeddableSeparator];
    }

    /**
     * Prepare the hydrator by adding strategies to every collection valued associations
     */
    private function prepare(object $object): void
    {
        $this->metadata = $this->objectManager->getClassMetadata($object::class);
        $this->prepareStrategies();
    }

    /**
     * Prepare strategies before the hydrator is used
     *
     * @throws InvalidArgumentException
     */
    private function prepareStrategies(): void
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
     */
    private function extractByValue(object $object): array
    {
        $methods = get_class_methods($object);
        $filter  = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        $data        = [];
        $embeddables = $this->getEmbeddables();
        foreach ($this->getFieldNames() as $fieldName) {
            if ($filter && ! $filter->filter($fieldName)) {
                continue;
            }

            [$found, $value] = $this->readValueByGetter($object, $fieldName, $methods);
            if (! $found) {
                // Unknown fields are ignored
                continue;
            }

            $this->addExtractedValue($data, $fieldName, $value, $object, $embeddables);
        }

        return $data;
    }

    /**
     * Extract values from an object using a by-reference logic (this means that values are
     * directly fetched without using the public API of the entity, in this case, getters)
     *
     * @return array<string,mixed>
     */
    private function extractByReference(object $object): array
    {
        $refl   = $this->getClassMetadata()->getReflectionClass();
        $filter = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        $data        = [];
        $embeddables = $this->getEmbeddables();

        // fail for readonly classes
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

            // skip uninitialized properties
            if (! $reflProperty->isInitialized($object)) {
                continue;
            }

            $this->addExtractedValue($data, $fieldName, $reflProperty->getValue($object), $object, $embeddables);
        }

        return $data;
    }

    /**
     * Reads a value using the getter or isser of a field
     *
     * @param list<string> $methods The methods of the object
     *
     * @return array{bool, mixed} Whether a method has been found, and the value
     */
    private function readValueByGetter(object $object, string $fieldName, array $methods): array
    {
        $getter = 'get' . $this->inflector->classify($fieldName);
        $isser  = 'is' . $this->inflector->classify($fieldName);

        if (in_array($getter, $methods, true)) {
            return [true, $object->$getter()];
        }

        if (in_array($isser, $methods, true)) {
            return [true, $object->$isser()];
        }

        if (str_starts_with($fieldName, 'is') && ctype_upper(substr($fieldName, 2, 1)) && in_array($fieldName, $methods, true)) {
            return [true, $object->$fieldName()];
        }

        return [false, null];
    }

    /**
     * Adds an extracted value to the data, converting associations and embeddables according to the settings
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $embeddables The embeddables of the class, see getEmbeddables()
     */
    private function addExtractedValue(array &$data, string $fieldName, mixed $value, object $object, array $embeddables): void
    {
        $value            = $this->extractValue($fieldName, $value, $object);
        $embeddableFields = $embeddables[$fieldName] ?? null;
        $context          = $this->getEmbeddableContext();

        // Values that are no embeddable objects, e.g. because a strategy converted them, are left untouched
        if (! is_array($embeddableFields) || ! is_object($value) || $context['mode'] === EmbeddableExtraction::Object) {
            $data[$this->computeExtractFieldName($fieldName)] = $this->extractAssociation($fieldName, $value);

            return;
        }

        $values = $this->extractEmbeddable($value, $embeddableFields);

        if ($context['mode'] === EmbeddableExtraction::Array) {
            $data[$this->computeExtractFieldName($fieldName)] = $values;

            return;
        }

        // Fails for keys colliding with other fields
        $this->getFlattenedFieldNames($embeddables, $context['separator']);
        $this->addFlattenedValues($data, $values, $embeddableFields, $this->computeExtractFieldName($fieldName), $context['separator']);
    }

    /**
     * Returns the embeddables of the class. Each embeddable maps the names of its fields to the full name
     * of the field, e.g. 'address' => ['postalCode' => 'address.postalCode'], or to the fields of a
     * nested embeddable.
     *
     * @return array<string, mixed>
     */
    private function getEmbeddables(): array
    {
        $embeddables = [];
        foreach ($this->getClassMetadata()->getFieldNames() as $fieldName) {
            $segments = explode('.', $fieldName);
            if (count($segments) < 2) {
                continue;
            }

            $fieldOfEmbeddable = array_pop($segments);
            $embeddable        = &$embeddables;
            foreach ($segments as $segment) {
                $embeddable[$segment] ??= [];
                $embeddable             = &$embeddable[$segment];
            }

            $embeddable[$fieldOfEmbeddable] = $fieldName;
            unset($embeddable);
        }

        return $embeddables;
    }

    /**
     * Maps the keys of the flattened fields of embeddables to the full names of the fields,
     * e.g. 'address__postal_code' => 'address.postalCode'
     *
     * @param array<string, mixed> $embeddables The embeddables of the class, see getEmbeddables()
     *
     * @return array<string, string>
     *
     * @throws LogicException If a key equals the key of another field.
     */
    private function getFlattenedFieldNames(array $embeddables, string $separator): array
    {
        $flattened = [];
        foreach ($this->getClassMetadata()->getFieldNames() as $fieldName) {
            if (! str_contains($fieldName, '.')) {
                continue;
            }

            $key = implode($separator, array_map(
                fn (string $segment): string => $this->computeExtractFieldName($segment),
                explode('.', $fieldName),
            ));

            if (isset($flattened[$key])) {
                throw new LogicException(sprintf(
                    'The fields "%s" and "%s" are both flattened to the key "%s", use a different separator',
                    $flattened[$key],
                    $fieldName,
                    $key,
                ));
            }

            $flattened[$key] = $fieldName;
        }

        foreach ($this->getFieldNames() as $fieldName) {
            $key = $this->computeExtractFieldName($fieldName);
            if (isset($embeddables[$fieldName]) || ! isset($flattened[$key])) {
                continue;
            }

            throw new LogicException(sprintf(
                'The field "%s" is flattened to the key "%s" of the field "%s", use a different separator',
                $flattened[$key],
                $key,
                $fieldName,
            ));
        }

        return $flattened;
    }

    /**
     * Extracts the fields of an embeddable into an array
     *
     * @param array<string, mixed> $fields The fields of the embeddable, see getEmbeddables()
     *
     * @return array<string, mixed>
     */
    private function extractEmbeddable(object $embeddable, array $fields): array
    {
        $values = [];
        foreach ($fields as $name => $field) {
            [$found, $value] = $this->readEmbeddedValue($embeddable, $name);
            if (! $found) {
                continue;
            }

            $values[$this->computeExtractFieldName($name)] = match (true) {
                is_array($field) && is_object($value) => $this->extractEmbeddable($value, $field),
                is_string($field) => $this->extractValue($field, $value, $embeddable),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Adds the extracted values of an embeddable to the data, prefixing them by the name of the embeddable
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $values The extracted values of the embeddable
     * @param array<string, mixed> $fields The fields of the embeddable, see getEmbeddables()
     */
    private function addFlattenedValues(array &$data, array $values, array $fields, string $prefix, string $separator): void
    {
        foreach ($fields as $name => $field) {
            $key = $this->computeExtractFieldName($name);
            if (! array_key_exists($key, $values)) {
                continue;
            }

            if (is_array($field) && is_array($values[$key])) {
                $this->addFlattenedValues($data, $values[$key], $field, $prefix . $separator . $key, $separator);
                continue;
            }

            $data[$prefix . $separator . $key] = $values[$key];
        }
    }

    /**
     * Reads the value of a field of an entity or an embeddable, using a getter when hydrating by value
     *
     * @return array{bool, mixed} Whether the field could be read, and the value
     */
    private function readEmbeddedValue(object $object, string $name): array
    {
        if ($this->byValue) {
            return $this->readValueByGetter($object, $name, get_class_methods($object));
        }

        if (! property_exists($object, $name)) {
            return [false, null];
        }

        $property = new ReflectionProperty($object, $name);
        if ($property->isReadOnly() || ! $property->isInitialized($object)) {
            return [false, null];
        }

        return [true, $property->getValue($object)];
    }

    /**
     * Writes the value of a field of an entity or an embeddable, using a setter when hydrating by value
     *
     * @throws LogicException
     */
    private function writeEmbeddedValue(object $object, string $name, mixed $value): void
    {
        if ($this->byValue) {
            $setter = 'set' . $this->inflector->classify($name);
            if (is_callable([$object, $setter])) {
                $object->$setter($value);
            }

            return;
        }

        if (! property_exists($object, $name)) {
            return;
        }

        $property = new ReflectionProperty($object, $name);
        if ($property->isReadOnly()) {
            throw new LogicException(sprintf(
                'Cannot hydrate class "%s" by reference. Property "%s" is readonly. To fix this error, remove readonly.',
                $object::class,
                $name,
            ));
        }

        $property->setValue($object, $value);
    }

    /**
     * Hydrates the embeddables of an object from nested arrays or flattened keys, depending on the
     * embeddable extraction, and returns the remaining data
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function hydrateEmbeddables(array $data, object $object): array
    {
        $context = $this->getEmbeddableContext();
        if ($context['mode'] === EmbeddableExtraction::Object) {
            return $data;
        }

        $embeddables = $this->getEmbeddables();
        if ($embeddables === []) {
            return $data;
        }

        // The values of the embeddables, keyed by the names of the fields
        $values = [];

        if ($context['mode'] === EmbeddableExtraction::Array) {
            foreach ($data as $key => $value) {
                $fieldName = $this->computeHydrateFieldName((string) $key);
                if (! is_array($embeddables[$fieldName] ?? null) || ! is_array($value)) {
                    continue;
                }

                $values[$fieldName] = $this->normalizeEmbeddableData($value, $embeddables[$fieldName]);
                unset($data[$key]);
            }
        } else {
            foreach ($this->getFlattenedFieldNames($embeddables, $context['separator']) as $key => $fieldName) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $segments  = explode('.', $fieldName);
                $container = &$values;
                foreach ($segments as $segment) {
                    $container[$segment] ??= [];
                    $container             = &$container[$segment];
                }

                $container = $data[$key];
                unset($container, $data[$key]);
            }
        }

        foreach ($values as $name => $embeddableValues) {
            $this->hydrateEmbeddable($object, $name, $name, $embeddables[$name], $embeddableValues, $data);
        }

        return $data;
    }

    /**
     * Converts the keys of nested data of an embeddable to the names of its fields, ignoring unknown fields
     *
     * @param array<array-key, mixed> $values
     * @param array<string, mixed>    $fields The fields of the embeddable, see getEmbeddables()
     *
     * @return array<string, mixed>
     */
    private function normalizeEmbeddableData(array $values, array $fields): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $name = $this->computeHydrateFieldName((string) $key);
            if (! isset($fields[$name])) {
                continue;
            }

            $normalized[$name] = is_array($fields[$name]) && is_array($value)
                ? $this->normalizeEmbeddableData($value, $fields[$name])
                : $value;
        }

        return $normalized;
    }

    /**
     * Hydrates an embeddable of an object, creating the embeddable if the object has none yet
     *
     * @param string                  $path   The full name of the embeddable, e.g. "address" or "address.geo"
     * @param array<string, mixed>    $fields The fields of the embeddable, see getEmbeddables()
     * @param array<string, mixed>    $values The values of the fields
     * @param array<array-key, mixed> $data   The whole data, provided as context to strategies
     */
    private function hydrateEmbeddable(object $object, string $name, string $path, array $fields, array $values, array $data): void
    {
        [$found, $embeddable] = $this->readEmbeddedValue($object, $name);
        if (! $found || ! is_object($embeddable)) {
            $className = $this->getEmbeddableClass($object, $name, $path);
            if ($className === null) {
                return;
            }

            $embeddable = $this->instantiate($className, $values);
        }

        foreach ($values as $fieldName => $value) {
            $field = $fields[$fieldName];

            if (is_array($field) && is_array($value)) {
                $this->hydrateEmbeddable($embeddable, $fieldName, $path . '.' . $fieldName, $field, $value, $data);
                continue;
            }

            $this->writeEmbeddedValue($embeddable, $fieldName, is_string($field) ? $this->hydrateValue($field, $value, $data) : $value);
        }

        $this->writeEmbeddedValue($object, $name, $embeddable);
    }

    /**
     * Returns the class of an embeddable, from the metadata of Doctrine ORM (an array in ORM 2,
     * an object in ORM 3) or from the type of the property
     *
     * @return class-string|null
     */
    private function getEmbeddableClass(object $object, string $name, string $path): string|null
    {
        $metadata = $this->getClassMetadata();
        if (property_exists($metadata, 'embeddedClasses') && is_array($metadata->embeddedClasses)) {
            $mapping   = $metadata->embeddedClasses[$path] ?? null;
            $className = is_array($mapping) ? ($mapping['class'] ?? null) : ($mapping->class ?? null);
            if (is_string($className) && class_exists($className)) {
                return $className;
            }
        }

        if (! property_exists($object, $name)) {
            return null;
        }

        $type = (new ReflectionProperty($object, $name))->getType();

        return $type instanceof ReflectionNamedType && ! $type->isBuiltin() && class_exists($type->getName())
            ? $type->getName()
            : null;
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
     * @param array<string,mixed> $data
     * @phpstan-param T $object
     *
     * @phpstan-return T
     *
     * @template T of object
     */
    private function hydrateByValue(array $data, object $object): object
    {
        $tryObject = $this->tryConvertArrayToObject($data, $object);
        $metadata  = $this->getClassMetadata();

        if (is_object($tryObject)) {
            $object = $tryObject;
        }

        $data = $this->hydrateEmbeddables($data, $object);

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
     * @param array<string,mixed> $data
     * @phpstan-param T $object
     *
     * @phpstan-return T
     *
     * @template T of object
     */
    private function hydrateByReference(array $data, object $object): object
    {
        $tryObject = $this->tryConvertArrayToObject($data, $object);
        $metadata  = $this->getClassMetadata();
        $refl      = $metadata->getReflectionClass();

        if (is_object($tryObject)) {
            $object = $tryObject;
        }

        $data = $this->hydrateEmbeddables($data, $object);

        foreach ($data as $field => $value) {
            $field = $this->computeHydrateFieldName($field);

            // Ignore unknown fields
            if (! $refl->hasProperty($field)) {
                continue;
            }

            $reflProperty = $refl->getProperty($field);

            // fail for readonly property
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
    private function tryConvertArrayToObject(array $data, object $object): object|null
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
    private function toOne(string $target, mixed $value): object|null
    {
        $metadata = $this->objectManager->getClassMetadata($target);

        if (is_array($value) && array_keys($value) !== $metadata->getIdentifier()) {
            // $value is most likely an array of fieldset data
            $identifiers = array_intersect_key(
                $value,
                array_flip($metadata->getIdentifier()),
            );
            $object      = $this->find($identifiers, $target) ?: $this->instantiate($target, $value);

            return $this->getRoot()->resolveAssociationHydrator($target)->hydrateWithContext($value, $object, $this->getEmbeddableContext());
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
    private function toMany(object $object, string $collectionName, string $target, mixed $values): void
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
                // Empty values, e.g. empty strings from forms, are looked up as identifier and ignored if not found
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
                        ? $this->getRoot()->resolveAssociationHydrator($target)->hydrateWithContext($value, $found, $this->getEmbeddableContext())
                        : $found;
                    continue;
                }
            }

            $collection[] = is_array($value)
                ? $this->getRoot()->resolveAssociationHydrator($target)->hydrateWithContext(
                    $value,
                    $this->instantiate($target, $value),
                    $this->getEmbeddableContext(),
                )
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

        // Hydrate like any other field: the strategy first, then the type conversion
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
    private function find(mixed $identifiers, string $targetClass): object|null
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
     */
    private function isNullIdentifier(mixed $identifier): bool
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
    private function computeHydrateFieldName(string $field): string
    {
        if ($this->hasNamingStrategy()) {
            $field = $this->getNamingStrategy()->hydrate($field);
        }

        return $field;
    }

    /**
     * Applies the naming strategy if there is one set
     */
    private function computeExtractFieldName(string $field): string
    {
        if ($this->hasNamingStrategy()) {
            $field = $this->getNamingStrategy()->extract($field);
        }

        return $field;
    }
}
