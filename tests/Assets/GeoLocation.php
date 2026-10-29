<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class GeoLocation
{
    protected float|null $lat = null;

    protected float|null $lng = null;

    public function getLat(): float|null
    {
        return $this->lat;
    }

    public function setLat(float|null $lat): void
    {
        $this->lat = $lat;
    }

    public function getLng(): float|null
    {
        return $this->lng;
    }

    public function setLng(float|null $lng): void
    {
        $this->lng = $lng;
    }
}
