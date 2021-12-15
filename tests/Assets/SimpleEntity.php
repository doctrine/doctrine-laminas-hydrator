<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntity
{
    /** @var string|int */
    protected $id;

    protected string $field;

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

    public function setField(string $field)
    {
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
