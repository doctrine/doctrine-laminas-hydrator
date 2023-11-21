<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class NamingStrategyEntity
{
    public function __construct(protected string|null $camelCase = null)
    {
    }

    public function setCamelCase(string|null $camelCase): void
    {
        $this->camelCase = $camelCase;
    }

    public function getCamelCase(): string|null
    {
        return $this->camelCase;
    }
}
