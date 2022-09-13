<?php

namespace App\DTO\V2\User;

use App\Entity\User;

class FullUserInfoResponseWithIsBlocked extends FullUserInfoResponse
{
    /** @var bool */
    public bool $isBlocked;

    public function __construct(
        User $user,
        bool $isFollowing,
        bool $isFollows,
        int $followers,
        int $following,
        bool $isBlocked
    ) {
        parent::__construct($user, $isFollowing, $isFollows, $followers, $following);

        $this->isBlocked = $isBlocked;
    }
}
