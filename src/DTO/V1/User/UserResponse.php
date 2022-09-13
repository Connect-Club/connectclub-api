<?php

namespace App\DTO\V1\User;

use App\DTO\V1\Interests\InterestDTO;
use App\Entity\Interest\Interest;
use App\DTO\V1\Location\CityResponse;
use App\DTO\V1\Location\CountryResponse;
use App\Entity\User;

class UserResponse
{
    /** @var int */
    public ?int $id;
    /** @var string */
    public ?string $email;
    /** @var string|null */
    public ?string $name;
    /** @var string|null */
    public ?string $surname;
    /** @var string|null */
    public ?string $company;
    /** @var string|null */
    public ?string $position;
    /** @var string|null */
    public ?string $about;
    /** @var string|null */
    public ?string $phone;
    /** @var CountryResponse|null */
    public ?CountryResponse $country = null;
    /** @var CityResponse|null */
    public ?CityResponse $city = null;
    /** @var int */
    public ?int $createdAt;
    /** @var string|null */
    public ?string $avatarSrc;
    /** @var bool */
    public bool $deleted = false;
    /** @var InterestDTO[] */
    public array $interests = [];

    public function __construct(User $user)
    {
        $this->id = (int) $user->id;

        if ($user->deletedAt) {
            $this->email = '';
            $this->name = '';
            $this->surname = '';
            $this->company = '';
            $this->position = '';
            $this->about = '';
            $this->phone = '';
            $this->createdAt = 0;
            $this->avatarSrc = null;
            $this->deleted = true;

            return;
        }

        if ($user->city) {
            $this->city = new CityResponse($user->city);
            $this->country = new CountryResponse($user->city->country);
        }

        $this->email = $user->email;
        $this->name = $user->name;
        $this->surname = $user->surname;
        $this->about = $user->about;
        $this->createdAt = $user->createdAt;
        $this->avatarSrc = $user->getAvatarSrc();
        $this->deleted = false;
        $this->interests = array_map(fn(Interest $interest) => new InterestDTO($interest), $user->interests->toArray());
    }
}
