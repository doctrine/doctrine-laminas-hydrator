<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator\Strategy;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;
use ReflectionException;

use function get_class;
use function is_array;
use function method_exists;
use function spl_object_hash;
use function sprintf;
use function strcmp;

/**
 * @internal
 */
abstract class AbstractCollectionStrategy implements CollectionStrategyInterface
{
    /** @var string */
    protected $collectionName;

    /** @var ClassMetadata */
    protected $metadata;

    /** @var object */
    protected $object;

    /** @var Inflector */
    protected $inflector;

    public function __construct(?Inflector $inflector = null)
    {
        $this->inflector = $inflector ?? InflectorFactory::create()->build();
    }

    public function setCollectionName(string $collectionName): void
    {
        $this->collectionName = $collectionName;
    }

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    public function setClassMetadata(ClassMetadata $classMetadata): void
    {
        $this->metadata = $classMetadata;
    }

    public function getClassMetadata(): ClassMetadata
    {
        return $this->metadata;
    }

    public function setObject(object $object): void
    {
        $this->object = $object;
    }

    public function getObject(): object
    {
        return $this->object;
    }

    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param  mixed       $value  The original value.
     * @param  object|null $object (optional) The original object for context.
     *
     * @return mixed       Returns the value that should be extracted.
     */
    public function extract($value, ?object $object = null)
    {
        return $value;
    }

    /**
     * Return the collection by value (using the public API)
     *
     * @throws InvalidArgumentException
     */
    protected function getCollectionFromObjectByValue(): Collection
    {
        $object = $this->getObject();
        $getter = 'get' . $this->inflector->classify($this->getCollectionName());

        if (! method_exists($object, $getter)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The getter %s to access collection %s in object %s does not exist',
                    $getter,
                    $this->getCollectionName(),
                    get_class($object)
                )
            );
        }

        $collection = $object->$getter();

        if (is_array($collection)) {
            $collection = new ArrayCollection($collection);
        }

        return $collection;
    }

    /**
     * Return the collection by reference (not using the public API)
     *
     * @throws InvalidArgumentException|ReflectionException
     */
    protected function getCollectionFromObjectByReference(): Collection
    {
        $object       = $this->getObject();
        $refl         = $this->getClassMetadata()->getReflectionClass();
        $reflProperty = $refl->getProperty($this->getCollectionName());

        $reflProperty->setAccessible(true);

        return $reflProperty->getValue($object);
    }

    /**
     * This method is used internally by array_udiff to check if two objects are equal, according to their
     * SPL hash. This is needed because the native array_diff only compare strings
     */
    protected function compareObjects(object $a, object $b): int
    {
        return strcmp(spl_object_hash($a), spl_object_hash($b));
    }
}
