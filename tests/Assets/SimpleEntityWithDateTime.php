<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use DateTime;

class SimpleEntityWithDateTime
{
    protected int $id;

    protected DateTime|null $date = null;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setDate(DateTime|null $date = null): void
    {
        $this->date = $date;
    }

    public function getDate(): DateTime|null
    {
        return $this->date;
    }
}
