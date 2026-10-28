<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class TreeNodeEntity
{
    protected TreeNodeEntity|null $parent = null;

    /** @var Collection<int, TreeNodeEntity> */
    protected Collection $children;

    public function __construct(protected int $id, protected string $name)
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): TreeNodeEntity|null
    {
        return $this->parent;
    }

    /** @return Collection<int, TreeNodeEntity> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(TreeNodeEntity $child): void
    {
        $child->parent    = $this;
        $this->children[] = $child;
    }
}
