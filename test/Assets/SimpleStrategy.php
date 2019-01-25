<?php

declare(strict_types=1);

namespace DoctrineTest\Zend\Hydrator\Assets;

use Zend\Hydrator\Strategy\StrategyInterface;

class SimpleStrategy implements StrategyInterface
{
    public function extract($value)
    {
        return 'modified while extracting';
    }

    public function hydrate($value)
    {
        return 'modified while hydrating';
    }
}
