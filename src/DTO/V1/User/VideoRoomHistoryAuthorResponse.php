<?php

namespace App\DTO\V1\User;

use App\DTO\V1\Interests\InterestDTO;
use App\DTO\V1\Location\CityResponse;
use App\DTO\V1\Location\CountryResponse;
use App\DTO\V1\Location\City;
use App\DTO\V1\Location\Country;
use App\Entity\Interest\Interest;
use App\Entity\User;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomHistoryAuthorResponse
{
    /**
     * @var int|null
     * @Groups({"default"})
     */
    public ?int $id;

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $name;

    /**
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $surname;

    /**
     * @var CountryResponse|null
     * @Groups({"default"})
     */
    public ?CountryResponse $country = null;

    /**
     * @var CityResponse|null
     * @Groups({"default"})
     */
    public ?CityResponse $city = null;

    /**
     * @var bool
     * @Groups({"default"})
     */
    public bool $deleted = false;

    /**
     * @var InterestDTO[]
     * @Groups({"default"})
     */
    public array $interests = [];

    public function __construct(User $user)
    {
        $this->id = $user->id;

        if ($user->deletedAt) {
            $this->deleted = true;
            $this->name = '';
            $this->surname = '';
            return;
        }

        if ($user->city) {
            $this->city = new CityResponse($user->city);
            $this->country = new CountryResponse($user->city->country);
        }

        $this->name = $user->name;
        $this->surname = $user->surname;
        $this->interests = array_map(fn(Interest $interest) => new InterestDTO($interest), $user->interests->toArray());
    }
}
