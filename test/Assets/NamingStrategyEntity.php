<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class NamingStrategyEntity
{
    /** @var null|string */
    protected $camelCase;

    public function __construct(?string $camelCase = null)
    {
        $this->camelCase = $camelCase;
    }

    public function setCamelCase(?string $camelCase)
    {
        $this->camelCase = $camelCase;
    }

    public function getCamelCase(): ?string
    {
        return $this->camelCase;
    }
}
