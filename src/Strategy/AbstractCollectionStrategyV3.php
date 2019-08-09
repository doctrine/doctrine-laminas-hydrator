<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
abstract class AbstractCollectionStrategyV3 extends AbstractCollectionStrategyInternal
{
    /**
     * {@inheritdoc}
     */
    public function extract($value, ?object $object = null)
    {
        return parent::extractInternal($value);
    }
}
