<?php

namespace App\DTO\V2\User;

use App\Entity\User;

class UserInfoResponse
{
    /** @var string */
    public string $id;

    /** @var string|null */
    public ?string $avatar;

    /** @var string|null */
    public ?string $name;

    /** @var string|null */
    public ?string $surname;

    /** @var string */
    public string $displayName;

    /** @var string */
    public string $description;

    /** @var string */
    public string $about;

    /** @var string */
    public string $username;

    /** @var bool */
    public bool $isDeleted;

    /** @var int */
    public int $createdAt;

    /** @var int */
    public int $lastSeen;

    /** @var bool */
    public bool $online;

    /** @var string[] */
    public array $badges = [];

    /** @var string|null */
    public ?string $shortBio = null;

    /** @var string|null */
    public ?string $longBio = null;

    /** @var string|null */
    public ?string $twitter = null;

    /** @var string|null */
    public ?string $linkedin = null;

    /** @var string|null */
    public ?string $instagram = null;

    public function __construct(User $user)
    {
        $userDisabled = $user->deleted !== null || $user->bannedAt !== null;
        
        $this->id = $userDisabled ? '0' : (string) $user->id;
        $this->avatar = $userDisabled ? null : $user->getAvatarSrc();
        $this->name = $userDisabled ? 'Deleted' : $user->name;
        $this->surname = $userDisabled ? 'User' : $user->surname;
        $this->displayName = $userDisabled ? 'Deleted User' : $user->getFullNameOrId();

        if ($user->bannedAt) {
            $this->name = 'Banned';
            $this->surname = 'User';
            $this->displayName = 'Banned User';
        }

        $this->about = $userDisabled ? '' : $user->about ?? '';
        $this->username = $userDisabled ? 'deleted' : $user->username ?? '';
        $this->isDeleted = $userDisabled || $user->bannedAt !== null;
        $this->createdAt = $userDisabled ? 0 : $user->createdAt;
        $this->online = !$userDisabled && $user->isOnline();
        $this->lastSeen = $userDisabled ? 0 : ($user->lastTimeActivity ?? $user->createdAt);
        $this->badges = $user->badges ?? [];
        $this->shortBio = $user->shortBio;
        $this->longBio = $user->longBio;

        $this->twitter = $user->twitter;
        $this->linkedin = $user->linkedin;
        $this->instagram = $user->instagram;
    }
}
