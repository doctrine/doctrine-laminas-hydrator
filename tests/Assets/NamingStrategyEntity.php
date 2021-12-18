<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class NamingStrategyEntity
{
    protected ?string $camelCase = null;

    public function __construct(?string $camelCase = null)
    {
        $this->camelCase = $camelCase;
    }

    public function setCamelCase(?string $camelCase): void
    {
        $this->camelCase = $camelCase;
    }

    public function getCamelCase(): ?string
    {
        return $this->camelCase;
    }
}
