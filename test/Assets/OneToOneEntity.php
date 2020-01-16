<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use DateTime;

class OneToOneEntity
{
    protected int $id;

    protected ByValueDifferentiatorEntity $toOne;

    protected DateTime $createdAt;

    public function setId($id) : void
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setToOne(?ByValueDifferentiatorEntity $entity = null, $modifyValue = true) : void
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue && $entity !== null) {
            $entity->setField('Modified from setToOne setter', false);
        }

        $this->toOne = $entity;
    }

    public function getToOne($modifyValue = true)
    {
        // Make some modifications to the association so that we can demonstrate difference between
        // by value and by reference
        if ($modifyValue) {
            $this->toOne->setField('Modified from getToOne getter', false);
        }

        return $this->toOne;
    }

    public function setCreatedAt(DateTime $createdAt) : void
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
