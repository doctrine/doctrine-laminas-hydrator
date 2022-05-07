<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use const PHP_VERSION_ID;

if (PHP_VERSION_ID >= 80100) {
    enum SimpleEnum: int
    {
        case One = 1;
        case Two = 2;
    }
}
