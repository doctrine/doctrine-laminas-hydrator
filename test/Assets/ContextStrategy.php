<?php

declare(strict_types=1);

namespace ZendTest\Doctrine\Hydrator\Assets;

use Zend\Hydrator\Strategy\StrategyInterface;

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
