<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator\Strategy;

use Doctrine\Common\Collections\Collection;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;
use Laminas\Hydrator\Strategy\StrategyInterface;

use function get_class;
use function is_object;
use function method_exists;
use function spl_object_hash;
use function sprintf;
use function strcmp;

// phpcs:disable SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractCollectionStrategy implements StrategyInterface
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

    /**
     * Set the name of the collection
     *
     * @param  string $collectionName
     *
     * @return $this
     */
    public function setCollectionName($collectionName)
    {
        $this->collectionName = (string) $collectionName;

        return $this;
    }

    /**
     * Get the name of the collection
     *
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collectionName;
    }

    /**
     * Set the class metadata
     *
     * @return $this
     */
    public function setClassMetadata(ClassMetadata $classMetadata)
    {
        $this->metadata = $classMetadata;

        return $this;
    }

    /**
     * Get the class metadata
     *
     * @return ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set the object
     *
     * @param  object $object
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setObject($object)
    {
        if (! is_object($object)) {
            throw new InvalidArgumentException(
                sprintf('The parameter given to setObject method of %s class is not an object', static::class)
            );
        }

        $this->object = $object;

        return $this;
    }

    /**
     * Get the object
     *
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param  mixed       $value  The original value.
     * @param object|null $object (optional) The original object for context.
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
     * @return Collection
     *
     * @throws InvalidArgumentException
     */
    protected function getCollectionFromObjectByValue()
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

        return $object->$getter();
    }

    /**
     * Return the collection by reference (not using the public API)
     *
     * @return Collection
     */
    protected function getCollectionFromObjectByReference()
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
     *
     * @param object $a
     * @param object $b
     *
     * @return int
     */
    protected function compareObjects($a, $b)
    {
        return strcmp(spl_object_hash($a), spl_object_hash($b));
    }
}
