<?php

namespace App\DTO\V1\User;

use App\DTO\V1\Interests\InterestDTO;
use App\DTO\V1\Location\City;
use App\DTO\V1\Location\Country;
use App\Entity\Interest\Interest;
use App\DTO\V1\Location\CityResponse;
use App\DTO\V1\Location\CountryResponse;
use App\Entity\User;
use Symfony\Component\Serializer\Annotation\Groups;

class UserInfoResponse
{
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
     * @var string|null
     * @Groups({"default"})
     */
    public ?string $avatarSrc;

    /**
     * @var bool
     * @Groups({"default"})
     */
    public bool $deleted = false;

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

    /** @var InterestDTO[] */
    public array $interests = [];

    /** @var string[] */
    public array $badges = [];

    /** @var string|null */
    public ?string $shortBio = null;

    /** @var string|null */
    public ?string $longBio = null;

    public function __construct(User $user)
    {
        if ($user->deletedAt) {
            $this->name = '';
            $this->surname = '';
            $this->avatarSrc = null;
            $this->deleted = true;

            return;
        }

        if ($user->city) {
            $this->city = new CityResponse($user->city);
            $this->country = new CountryResponse($user->city->country);
        }

        $this->name = $user->name;
        $this->surname = $user->surname;
        $this->avatarSrc = $user->getAvatarSrc();
        $this->interests = array_map(fn(Interest $interest) => new InterestDTO($interest), $user->interests->toArray());
        $this->badges = $user->badges ?? [];
        $this->shortBio = $user->shortBio;
        $this->longBio = $user->longBio;
    }
}
