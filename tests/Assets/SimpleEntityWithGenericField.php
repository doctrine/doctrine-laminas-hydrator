<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithGenericField
{
    protected int $id;

    /** @var mixed */
    protected $genericField;

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param mixed $value
     */
    public function setGenericField($value)
    {
        $this->genericField = $value;
    }

    /**
     * @return mixed
     */
    public function getGenericField()
    {
        return $this->genericField;
    }
}
