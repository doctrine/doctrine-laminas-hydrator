<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class OneToOneEntityNotNullable
{
    protected int $id;

    protected ByValueDifferentiatorEntity $toOne;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setToOne(ByValueDifferentiatorEntity $entity, bool $modifyValue = true): void
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue) {
            $entity->setField('Modified from setToOne setter', false);
        }

        $this->toOne = $entity;
    }

    public function getToOne(bool $modifyValue = true): ByValueDifferentiatorEntity|null
    {
        // Make some modifications to the association so that we can demonstrate difference between
        // by value and by reference
        if ($modifyValue) {
            $this->toOne->setField('Modified from getToOne getter', false);
        }

        return $this->toOne;
    }
}
