<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithIsBoolean
{
    protected int $id;

    protected bool $isActive;

    public function setId($id) : void
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setIsActive($isActive) : void
    {
        $this->isActive = (bool) $isActive;
    }

    public function isActive()
    {
        return $this->isActive;
    }

    public function getIsActive()
    {
        return $this->isActive();
    }
}
