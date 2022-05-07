<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Laminas\Hydrator\Strategy\StrategyInterface;

if (PHP_VERSION_ID >= 80100) {
    class SimpleEnumStrategy implements StrategyInterface
    {
        /**
         * @param mixed $value
         *
         * @return int|null
         */
        public function extract($value, ?object $object = null)
        {
            if ($value === null) {
                return null;
            }

            return SimpleEnum::tryFrom($value)->value;
        }

        /**
         * @param mixed                        $value
         * @param array<array-key, mixed>|null $data
         *
         * @return SimpleEnum|null
         */
        public function hydrate($value, ?array $data)
        {
            if ($value === null) {
                return null;
            }

            return SimpleEnum::tryFrom($value);
        }
    }
}
