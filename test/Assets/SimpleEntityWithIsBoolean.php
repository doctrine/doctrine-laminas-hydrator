<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithIsBoolean
{
    /** @var int */
    protected $id;

    /** @var bool */
    protected $isActive;

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setIsActive(bool $isActive)
    {
        $this->isActive = (bool) $isActive;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getIsActive(): bool
    {
        return $this->isActive();
    }
}
