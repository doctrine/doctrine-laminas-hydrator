<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntity
{
    protected int $id;

    protected string $field;

    public function setId($id) : void
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setField($field) : void
    {
        $this->field = $field;
    }

    public function getField()
    {
        return $this->field;
    }
}
