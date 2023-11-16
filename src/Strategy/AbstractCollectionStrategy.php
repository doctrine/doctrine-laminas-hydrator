<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator\Strategy;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;
use LogicException;
use ReflectionException;

use function is_array;
use function method_exists;
use function spl_object_hash;
use function sprintf;
use function strcmp;

/** @internal */
abstract class AbstractCollectionStrategy implements CollectionStrategyInterface
{
    private string|null $collectionName = null;

    private ClassMetadata|null $metadata = null;

    private object|null $object = null;

    private Inflector $inflector;

    public function __construct(Inflector|null $inflector = null)
    {
        $this->inflector = $inflector ?? InflectorFactory::create()->build();
    }

    public function setCollectionName(string $collectionName): void
    {
        $this->collectionName = $collectionName;
    }

    public function getCollectionName(): string
    {
        if ($this->collectionName === null) {
            throw new LogicException('Collection name has not been set.');
        }

        return $this->collectionName;
    }

    public function setClassMetadata(ClassMetadata $classMetadata): void
    {
        $this->metadata = $classMetadata;
    }

    public function getClassMetadata(): ClassMetadata
    {
        if ($this->metadata === null) {
            throw new LogicException('Class metadata has not been set.');
        }

        return $this->metadata;
    }

    public function setObject(object $object): void
    {
        $this->object = $object;
    }

    public function getObject(): object
    {
        if ($this->object === null) {
            throw new LogicException('Object has not been set.');
        }

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
    public function extract($value, object|null $object = null)
    {
        return $value;
    }

    protected function getInflector(): Inflector
    {
        return $this->inflector;
    }

    /**
     * Return the collection by value (using the public API)
     *
     * @return Collection<array-key,object>
     *
     * @throws InvalidArgumentException
     */
    protected function getCollectionFromObjectByValue(): Collection
    {
        $object = $this->getObject();
        $getter = 'get' . $this->getInflector()->classify($this->getCollectionName());

        if (! method_exists($object, $getter)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The getter %s to access collection %s in object %s does not exist',
                    $getter,
                    $this->getCollectionName(),
                    $object::class,
                ),
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
     * @return Collection<array-key,object>
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
        // see https://github.com/php/php-src/issues/10513
        return strcmp(spl_object_hash($a), spl_object_hash($b));
    }
}
