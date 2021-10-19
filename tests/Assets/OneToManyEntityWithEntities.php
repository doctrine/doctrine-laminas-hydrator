<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class OneToManyEntityWithEntities extends OneToManyEntity
{
    public function __construct(?ArrayCollection $entities = null)
    {
        $this->entities = $entities;
    }

    public function getEntities(bool $modifyValue = true): Collection
    {
        return $this->entities;
    }
}
