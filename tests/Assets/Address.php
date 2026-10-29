<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class Address
{
    protected string|null $postalCode = null;

    protected string|null $city = null;

    protected GeoLocation|null $geo = null;

    public function getPostalCode(): string|null
    {
        return $this->postalCode;
    }

    public function setPostalCode(string|null $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getCity(): string|null
    {
        return $this->city;
    }

    public function setCity(string|null $city): void
    {
        $this->city = $city;
    }

    public function getGeo(): GeoLocation|null
    {
        return $this->geo;
    }

    public function setGeo(GeoLocation|null $geo): void
    {
        $this->geo = $geo;
    }
}
