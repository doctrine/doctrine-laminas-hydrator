<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use function sprintf;

class ByValueDifferentiatorEntity
{
    /** @var string|int */
    protected $id;

    protected string $field;

    public function setId(string|int $id): void
    {
        $this->id = $id;
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function setField(string $field, bool $modifyValue = true): void
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue) {
            $this->field = sprintf('From setter: %s', $field);
        } else {
            $this->field = $field;
        }
    }

    public function getField(bool $modifyValue = true): string
    {
        // Modify the value to illustrate the difference between by value and by reference
        if ($modifyValue) {
            return sprintf('From getter: %s', $this->field);
        }

        return $this->field;
    }
}
