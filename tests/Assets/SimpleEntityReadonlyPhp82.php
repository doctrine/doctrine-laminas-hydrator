<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

readonly class SimpleEntityReadonlyPhp82
{
    protected ?int $id;

    protected ?string $field;

    public function __construct(?int $id, ?string $field)
    {
        $this->id    = $id;
        $this->field = $field;
    }
}
