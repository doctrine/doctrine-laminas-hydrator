<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class DisallowRemoveByReferenceV3 extends AbstractCollectionStrategy
{
    use DisallowRemoveByReferenceTrait;

    /**
     * @param mixed $value
     * @param null|array $data
     * @return mixed
     */
    public function hydrate($value, ?array $data)
    {
        return $this->hydrateInternal($value);
    }
}
