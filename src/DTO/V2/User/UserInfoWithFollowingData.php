<?php

namespace App\DTO\V2\User;

use App\Entity\User;

class UserInfoWithFollowingData extends UserInfoResponse
{
    /** @var bool */
    public bool $isFollowing;

    /** @var bool */
    public bool $isFollows;

    public function __construct(
        User $user,
        bool $isFollowing,
        bool $isFollows
    ) {
        parent::__construct($user);

        $this->isFollowing = $isFollowing;
        $this->isFollows = $isFollows;
    }
}
