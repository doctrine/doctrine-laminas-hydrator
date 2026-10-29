<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimpleEntityWithEmbeddable
{
    protected int $id;

    protected EmbeddableEntity $embedded;

    public function __construct()
    {
        $this->embedded = new EmbeddableEntity();
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setEmbedded(EmbeddableEntity $embedded): void
    {
        $this->embedded = $embedded;
    }

    public function getEmbedded(): EmbeddableEntity
    {
        return $this->embedded;
    }
}
