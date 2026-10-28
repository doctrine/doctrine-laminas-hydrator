<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class SimpleStrategy implements StrategyInterface
{
    public function extract(mixed $value, object|null $object = null): mixed
    {
        return 'modified while extracting';
    }

    /** @param array<array-key, mixed>|null $data */
    public function hydrate(mixed $value, array|null $data = null): mixed
    {
        return 'modified while hydrating';
    }
}
