<?php

namespace App\DTO\V1\Location;

use App\Entity\Location\City;

class CityResponse
{
    /** @var int */
    public int $id = 0;

    /** @var string */
    public string $name = '';

    public function __construct(?City $city)
    {
        if ($city) {
            $this->id = (int) $city->id;
            $this->name = $city->name;
        }
    }
}
