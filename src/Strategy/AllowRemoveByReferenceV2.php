<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class AllowRemoveByReferenceV2 extends AbstractCollectionStrategy
{
    use AllowRemoveByReferenceTrait;

    /**
     * @param mixed $value
     * @return mixed
     */
    public function hydrate($value)
    {
        return $this->hydrateInternal($value);
    }
}
