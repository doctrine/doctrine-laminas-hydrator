<?php

declare(strict_types=1);

namespace DoctrineTest\Zend\Hydrator\Assets;

class SimpleIsEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var bool
     */
    protected $done;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setDone($done)
    {
        $this->done = (bool) $done;
    }

    public function isDone()
    {
        return $this->done;
    }
}
