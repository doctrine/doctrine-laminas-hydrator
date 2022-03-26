<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithEnum
{
    protected int $id;

    protected ?SimpleEnum $enum;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setEnum(?SimpleEnum $enum = null): void
    {
        $this->enum = $enum;
    }

    public function getEnum(): ?SimpleEnum
    {
        return $this->enum;
    }
}
