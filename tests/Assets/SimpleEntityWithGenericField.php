<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithGenericField
{
    protected ?int $id = null;

    /** @var mixed */
    protected $genericField;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setGenericField(mixed $value): void
    {
        $this->genericField = $value;
    }

    /** @return mixed */
    public function getGenericField()
    {
        return $this->genericField;
    }
}
