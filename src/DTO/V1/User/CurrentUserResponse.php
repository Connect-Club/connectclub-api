<?php

namespace App\DTO\V1\User;

use App\DTO\V1\Interests\InterestDTO;
use App\Entity\Interest\Interest;
use App\DTO\V1\Location\CityResponse;
use App\DTO\V1\Location\CountryResponse;
use App\Entity\User;

class CurrentUserResponse extends UserResponse
{
    /** @var CountryResponse|null */
    public ?CountryResponse $country = null;

    /** @var CityResponse|null */
    public ?CityResponse $city = null;

    /** @var InterestDTO[] */
    public array $interests = [];

    /** @var string[] */
    public array $badges;

    /** @var string|null */
    public ?string $shortBio = null;

    /** @var string|null */
    public ?string $longBio = null;

    public function __construct(User $user)
    {
        $city = $user->city;
        if (!$city) {
            $country = $user->country;
        } else {
            $country = $city->country;
        }

        $this->country = new CountryResponse($country);
        $this->city = new CityResponse($city);
        $this->interests = array_map(fn(Interest $interest) => new InterestDTO($interest), $user->interests->toArray());
        $this->badges = $user->badges ?? [];
        $this->shortBio = $user->shortBio;
        $this->longBio = $user->longBio;

        parent::__construct($user);
    }
}
