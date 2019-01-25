<?php

declare(strict_types=1);

namespace DoctrineTest\Zend\Hydrator\Assets;

class SimpleEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $field;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setField($field)
    {
        $this->field = $field;
    }

    public function getField()
    {
        return $this->field;
    }
}
