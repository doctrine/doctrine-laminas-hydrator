<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class ByValueDifferentiatorEntity
{
    /** @var string|int */
    protected $id;

    /** @var string */
    protected $field;

    /**
     * @param string|int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string|int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setField(string $field, bool $modifyValue = true)
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue) {
            $this->field = "From setter: $field";
        } else {
            $this->field = $field;
        }
    }

    public function getField(bool $modifyValue = true): string
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue) {
            return "From getter: $this->field";
        }

        return $this->field;
    }
}
