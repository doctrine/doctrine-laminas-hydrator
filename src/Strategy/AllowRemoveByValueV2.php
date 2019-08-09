<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class AllowRemoveByValueV2 extends AbstractCollectionStrategy
{
    use AllowRemoveByValueTrait;

    /**
     * @param mixed $value
     * @return mixed
     */
    public function hydrate($value)
    {
        return $this->hydrateInternal($value);
    }
}
