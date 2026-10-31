<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class EntityWithConstructorArgument
{
    protected int|null $id = null;

    protected string|null $field = null;

    public function __construct(protected string $origin)
    {
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getField(): string|null
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }
}
