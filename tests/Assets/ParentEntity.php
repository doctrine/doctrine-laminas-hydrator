<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ParentEntity
{
    protected int|null $id = null;

    protected string|null $name = null;

    protected ChildEntity|null $child = null;

    /** @var Collection<array-key, ChildEntity> */
    protected Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getChild(): ChildEntity|null
    {
        return $this->child;
    }

    public function setChild(ChildEntity|null $child): void
    {
        $this->child = $child;
    }

    /** @return Collection<array-key, ChildEntity> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /** @param Collection<array-key, ChildEntity> $children */
    public function addChildren(Collection $children): void
    {
        foreach ($children as $child) {
            $this->children->add($child);
        }
    }

    /** @param Collection<array-key, ChildEntity> $children */
    public function removeChildren(Collection $children): void
    {
        foreach ($children as $child) {
            $this->children->removeElement($child);
        }
    }
}
