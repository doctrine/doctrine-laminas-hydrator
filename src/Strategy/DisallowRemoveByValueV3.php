<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class DisallowRemoveByValueV3 extends AbstractCollectionStrategy
{
    use DisallowRemoveByValueTrait;

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
