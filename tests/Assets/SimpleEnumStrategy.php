<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class SimpleEnumStrategy implements StrategyInterface
{
    public function extract(mixed $value, object|null $object = null): int|null
    {
        if ($value === null) {
            return null;
        }

        return SimpleEnum::tryFrom($value)->value;
    }

    /** @param array<array-key, mixed>|null $data */
    public function hydrate(mixed $value, array|null $data = null): SimpleEnum|null
    {
        if ($value === null) {
            return null;
        }

        return SimpleEnum::tryFrom($value);
    }
}
