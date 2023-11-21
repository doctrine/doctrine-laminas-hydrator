<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityPhp74
{
    protected int|null $id;

    protected string|null $field;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
