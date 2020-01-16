<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use DateTime;

class SimpleEntityWithDateTime
{
    protected int $id;

    protected DateTime $date;

    public function setId($id) : void
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setDate(?DateTime $date = null) : void
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }
}
