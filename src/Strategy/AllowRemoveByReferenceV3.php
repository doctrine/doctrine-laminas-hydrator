<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class AllowRemoveByReferenceV3 extends AbstractCollectionStrategy
{
    use AllowRemoveByReferenceTrait;

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
