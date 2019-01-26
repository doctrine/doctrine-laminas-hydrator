<?php

namespace Doctrine\Zend\Hydrator\Filter;

/**
 * @internal
 */
class PropertyNameV3 extends PropertyNameInternal
{
    public function filter(string $property) : bool
    {
        return in_array($property, $this->properties)
            ? ! $this->exclude
            : $this->exclude;
    }
}
