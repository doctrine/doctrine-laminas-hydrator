<?php

declare(strict_types=1);

namespace ZendTest\Doctrine\Hydrator\Assets;

use Doctrine\Common\Collections\ArrayCollection;

class OneToManyEntityWithEntities extends OneToManyEntity
{
    public function __construct(ArrayCollection $entities = null)
    {
        $this->entities = $entities;
    }

    public function getEntities($modifyValue = true)
    {
        return $this->entities;
    }
}
