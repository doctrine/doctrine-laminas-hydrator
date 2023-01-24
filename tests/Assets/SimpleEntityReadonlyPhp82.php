<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

readonly class SimpleEntityReadonlyPhp82
{
    public function __construct(protected ?int $id, protected ?string $field)
    {
    }
}
