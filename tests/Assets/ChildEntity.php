<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class ChildEntity
{
    protected int|null $id = null;

    protected string|null $name = null;

    protected string|null $displayName = null;

    /** Has no setter, so it can only be hydrated by reference */
    protected string|null $code = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDisplayName(): string|null
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getCode(): string|null
    {
        return $this->code;
    }
}
