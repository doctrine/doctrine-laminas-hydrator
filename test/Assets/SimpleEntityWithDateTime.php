<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use DateTime;

class SimpleEntityWithDateTime
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var DateTime
     */
    protected $date;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setDate(DateTime $date = null)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }
}
