<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class EmbedabbleEntity
{
    protected string $field = '';

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
