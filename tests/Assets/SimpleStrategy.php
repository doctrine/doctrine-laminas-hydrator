<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class SimpleStrategy implements StrategyInterface
{
    /**
     * @param  mixed $value
     *
     * @return mixed
     */
    public function extract($value, object|null $object = null)
    {
        return 'modified while extracting';
    }

    /**
     * @param mixed                        $value
     * @param array<array-key, mixed>|null $data
     *
     * @return mixed
     */
    public function hydrate($value, array|null $data)
    {
        return 'modified while hydrating';
    }
}
