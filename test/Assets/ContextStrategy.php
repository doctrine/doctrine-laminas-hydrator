<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class ContextStrategy implements StrategyInterface
{
    public function extract($value, $object = null)
    {
        return (string) $value . $object->getField();
    }

    public function hydrate($value, $data = null)
    {
        return $value . $data['field'];
    }
}
