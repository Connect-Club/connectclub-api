<?php

namespace App\DTO\V1\User;

use App\Entity\User;

class PopularUserItemResponse
{
    public string $name;
    public string $surname;
    public string $avatar;
    public string $username;
    public int $count;

    public function __construct(User $user, int $count)
    {
        $this->name = $user->name ?? '';
        $this->surname = $user->surname ?? '';
        $this->avatar = $user->getAvatarSrc() ?? '';
        $this->username = $user->username ?? '';
        $this->count = $count;
    }
}
