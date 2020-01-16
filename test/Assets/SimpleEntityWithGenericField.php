<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithGenericField
{
    protected int $id;

    protected float $genericField;

    public function setId($id) : void
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setGenericField($value)
    {
        $this->genericField = $value;

        return $this;
    }

    public function getGenericField()
    {
        return $this->genericField;
    }
}
