<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class NamingStrategyEntity
{
    public function __construct(protected ?string $camelCase = null)
    {
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
