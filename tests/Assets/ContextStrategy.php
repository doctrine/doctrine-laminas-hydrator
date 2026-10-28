<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class ContextStrategy implements StrategyInterface
{
    public function extract(mixed $value, object|null $object = null): mixed
    {
        return (string) $value . $object->getField();
    }

    /** @param array<array-key, mixed>|null $data */
    public function hydrate(mixed $value, array|null $data = null): mixed
    {
        return $value . $data['field'];
    }
}
