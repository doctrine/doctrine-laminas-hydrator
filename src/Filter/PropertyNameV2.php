<?php

namespace Doctrine\Zend\Hydrator\Filter;

/**
 * @internal
 */
class PropertyNameV2 extends PropertyNameInternal
{
    /**
     * {@inheritdoc}
     */
    public function filter($property)
    {
        return in_array($property, $this->properties)
            ? ! $this->exclude
            : $this->exclude;
    }
}
