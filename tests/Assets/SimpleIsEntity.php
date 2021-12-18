<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleIsEntity
{
    /** @var int|string */
    protected $id;

    protected bool $done;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setDone(bool $done): void
    {
        $this->done = $done;
    }

    public function isDone(): bool
    {
        return $this->done;
    }
}
