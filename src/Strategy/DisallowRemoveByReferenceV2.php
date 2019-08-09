<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class DisallowRemoveByReferenceV2 extends AbstractCollectionStrategy
{
    use DisallowRemoveByReferenceTrait;

    /**
     * @param mixed $value
     * @return mixed
     */
    public function hydrate($value)
    {
        return $this->hydrateInternal($value);
    }
}
