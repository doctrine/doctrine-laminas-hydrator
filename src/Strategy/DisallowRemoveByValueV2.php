<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class DisallowRemoveByValueV2 extends AbstractCollectionStrategy
{
    use DisallowRemoveByValueTrait;

    /**
     * @param mixed $value
     * @return mixed
     */
    public function hydrate($value)
    {
        return $this->hydrateInternal($value);
    }
}
