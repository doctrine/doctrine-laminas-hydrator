<?php

namespace Doctrine\Zend\Hydrator;

use Doctrine\Common\Persistence\ObjectManager;

class DoctrineObjectV3 extends DoctrineObjectInternal
{
    /**
     * @param ObjectManager $objectManager The ObjectManager to use
     * @param bool $byValue If set to true, hydrator will always use entity's public API
     */
    public function __construct(ObjectManager $objectManager, $byValue = true)
    {
        $this->objectManager = $objectManager;
        $this->byValue = (bool) $byValue;
    }

    /** Extract values from an object */
    public function extract(object $object) : array
    {
        return parent::extractInternal($object);
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @return object
     */
    public function hydrate(array $data, object $object)
    {
        return parent::hydrateInternal($data, $object);
    }

    public function hydrateValue(string $name, $value, ?array $data = null)
    {
        return $this->hydrateValueInternal($name, $value, $data);
    }
}
