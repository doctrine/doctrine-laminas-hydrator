<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
class AllowRemoveByValueV3 extends AbstractCollectionStrategy
{
    use AllowRemoveByValueTrait;

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
