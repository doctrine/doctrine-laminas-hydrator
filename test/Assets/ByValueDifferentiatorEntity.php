<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class ByValueDifferentiatorEntity
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

    public function setField($field, $modifyValue = true) : void
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue) {
            $this->field = "From setter: $field";
        } else {
            $this->field = $field;
        }
    }

    public function getField($modifyValue = true)
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue) {
            return "From getter: $this->field";
        }

        return $this->field;
    }
}
