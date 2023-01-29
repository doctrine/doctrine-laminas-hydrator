<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithReadonlyPropsPhp81
{
    protected ?string $field;

    public function __construct(protected readonly ?int $id)
    {
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
