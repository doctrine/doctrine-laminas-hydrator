<?php

namespace Doctrine\Zend\Hydrator\Strategy;

/**
 * @internal
 */
abstract class AbstractCollectionStrategyV2 extends AbstractCollectionStrategyInternal
{
    /**
     * {@inheritdoc}
     */
    public function extract($value)
    {
        return parent::extractInternal($value);
    }
}
