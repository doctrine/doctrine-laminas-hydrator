<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class OwnerOfEntitiesWithConstructorArgument
{
    protected int|null $id = null;

    protected EntityWithConstructorArgument|null $toOne = null;

    /** @var Collection<array-key, EntityWithConstructorArgument> */
    protected Collection $entities;

    public function __construct()
    {
        $this->entities = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getToOne(): EntityWithConstructorArgument|null
    {
        return $this->toOne;
    }

    public function setToOne(EntityWithConstructorArgument|null $toOne): void
    {
        $this->toOne = $toOne;
    }

    /** @return Collection<array-key, EntityWithConstructorArgument> */
    public function getEntities(): Collection
    {
        return $this->entities;
    }

    /** @param Collection<array-key, EntityWithConstructorArgument> $entities */
    public function addEntities(Collection $entities): void
    {
        foreach ($entities as $entity) {
            $this->entities->add($entity);
        }
    }

    /** @param Collection<array-key, EntityWithConstructorArgument> $entities */
    public function removeEntities(Collection $entities): void
    {
        foreach ($entities as $entity) {
            $this->entities->removeElement($entity);
        }
    }
}
