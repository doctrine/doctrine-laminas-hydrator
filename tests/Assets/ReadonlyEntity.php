<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

readonly class ReadonlyEntity
{
    public function __construct(protected int|null $id, protected string|null $field)
    {
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getField(): string|null
    {
        return $this->field;
    }
}
