<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class PersonWithAddress
{
    protected int|null $id = null;

    protected string|null $name = null;

    protected Address|null $address = null;

    protected PersonWithAddress|null $partner = null;

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

    public function getAddress(): Address|null
    {
        return $this->address;
    }

    public function setAddress(Address|null $address): void
    {
        $this->address = $address;
    }

    public function getPartner(): PersonWithAddress|null
    {
        return $this->partner;
    }

    public function setPartner(PersonWithAddress|null $partner): void
    {
        $this->partner = $partner;
    }
}
