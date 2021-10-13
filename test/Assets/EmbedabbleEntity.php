<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class EmbedabbleEntity
{
    /** @var string */
    protected $field;

    public function setField(string $field)
    {
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
