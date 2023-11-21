<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class ContextStrategy implements StrategyInterface
{
    /**
     * @param  mixed $value
     *
     * @return mixed
     */
    public function extract($value, object|null $object = null)
    {
        return (string) $value . $object->getField();
    }

    /**
     * @param mixed                        $value
     * @param array<array-key, mixed>|null $data
     *
     * @return mixed
     */
    public function hydrate($value, array|null $data = null)
    {
        return $value . $data['field'];
    }
}
