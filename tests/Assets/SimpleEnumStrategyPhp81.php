<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

class SimpleEnumStrategyPhp81 implements StrategyInterface
{
    /** @param mixed $value */
    public function extract($value, ?object $object = null): ?int
    {
        if ($value === null) {
            return null;
        }

        return SimpleEnumPhp81::tryFrom($value)->value;
    }

    /**
     * @param mixed                        $value
     * @param array<array-key, mixed>|null $data
     */
    public function hydrate($value, ?array $data): ?SimpleEnumPhp81
    {
        if ($value === null) {
            return null;
        }

        return SimpleEnumPhp81::tryFrom($value);
    }
}
