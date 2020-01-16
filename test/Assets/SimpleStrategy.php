<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class SimpleStrategy implements StrategyInterface
{
    public function extract($value, ?object $object = null)
    {
        return 'modified while extracting';
    }

    public function hydrate($value, ?array $data)
    {
        return 'modified while hydrating';
    }
}
