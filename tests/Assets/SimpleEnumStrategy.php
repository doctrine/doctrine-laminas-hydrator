<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class SimpleEnumStrategy implements StrategyInterface
{
    /**
     * @param  mixed $value
     */
    public function extract($value, ?object $object = null): int|null
    {
        if ($value === null) {
            return null;
        }

        return SimpleEnum::tryFrom($value)->value;
    }

    /**
     * @param mixed                        $value
     * @param array<array-key, mixed>|null $data
     */
    public function hydrate($value, ?array $data): SimpleEnum|null
    {
        if ($value === null) {
            return null;
        }

        return SimpleEnum::tryFrom($value);
    }
}
