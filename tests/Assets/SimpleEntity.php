<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntity
{
    /** @var string|int */
    protected $id;

    protected string $field;

    public function setId(string|int $id): void
    {
        $this->id = $id;
    }

    public function getId(): string|int
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
