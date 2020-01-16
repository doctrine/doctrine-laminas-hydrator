<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleIsEntity
{
    protected int $id;

    protected bool $done;

    public function setId($id) : void
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setDone($done) : void
    {
        $this->done = (bool) $done;
    }

    public function isDone()
    {
        return $this->done;
    }
}
