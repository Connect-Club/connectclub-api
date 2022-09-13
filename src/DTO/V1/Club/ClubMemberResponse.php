<?php

namespace App\DTO\V1\Club;

use App\DTO\V2\User\UserInfoWithFollowingData;
use App\Entity\User;

class ClubMemberResponse extends UserInfoWithFollowingData
{
    /** @var string */
    public string $clubRole;

    /** @var string */
    public string $role;

    public function __construct(User $user, bool $isFollowing, bool $isFollows, string $role)
    {
        parent::__construct($user, $isFollowing, $isFollows);

        $this->clubRole = $this->role = $role;
    }
}
