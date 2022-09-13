<?php

namespace App\DTO\V1\Club;

use App\Entity\User;

class ClubUser
{
    public int $id;
    public ?string $avatar;
    public ?string $displayName;

    public function __construct(User $user)
    {
        $userDisabled = $user->deleted !== null || $user->bannedAt !== null;

        $this->id = $user->id;
        $this->avatar = $user->avatar ? $user->avatar->getResizerUrl() : null;
        $this->displayName = $userDisabled ? 'Banned User' : $user->getFullNameOrId();
    }
}
