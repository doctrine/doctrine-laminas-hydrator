<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class SimpleStrategy implements StrategyInterface
{
    /**
     * @param  mixed $value
     * @return mixed
     */
    public function extract($value, ?object $object = null)
    {
        return 'modified while extracting';
    }

    /**
     * @param  mixed $value
     * @return mixed
     */
    public function hydrate($value, ?array $data)
    {
        return 'modified while hydrating';
    }
}
