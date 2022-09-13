<?php

namespace App\DTO\V1\Location;

use App\Entity\Location\Country;
use Symfony\Component\Serializer\Annotation\Groups;

class CountryResponse
{
    /**
     * @var int
     * @Groups({"v1.location.countries"})
     */
    public int $id = 0;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $name = '';

    public function __construct(?Country $country)
    {
        if ($country) {
            $this->id = (int) $country->id;
            $this->name = $country->name;
        }
    }
}
