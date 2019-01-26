<?php

namespace Doctrine\Zend\Hydrator;

use Doctrine\Common\Persistence\ObjectManager;

class DoctrineObjectV2 extends DoctrineObjectInternal
{
    /**
     * @param ObjectManager $objectManager The ObjectManager to use
     * @param bool $byValue If set to true, hydrator will always use entity's public API
     */
    public function __construct(ObjectManager $objectManager, $byValue = true)
    {
        parent::__construct();

        $this->objectManager = $objectManager;
        $this->byValue = (bool) $byValue;
    }

    /**
     * Extract values from an object
     *
     * @param  object $object
     * @return array
     */
    public function extract($object)
    {
        return $this->extractInternal($object);
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @param array $data
     * @param object $object
     * @return object
     */
    public function hydrate($data, $object)
    {
        return $this->hydrateInternal($data, $object);
    }

    public function hydrateValue($name, $value, $data = null)
    {
        return $this->hydrateValueInternal($name, $value, $data);
    }
}
