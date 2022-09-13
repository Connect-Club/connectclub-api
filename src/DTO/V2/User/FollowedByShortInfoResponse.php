<?php

namespace App\DTO\V2\User;

use App\Entity\User;

class FollowedByShortInfoResponse
{
    /** @var UserInfoResponse[] */
    public array $users;

    /** @var int */
    public int $totalCount;

    public function __construct(array $users, int $totalCount)
    {
        $this->users = array_map(fn(array $userData) => new UserInfoResponse($userData[0]), $users);
        $this->totalCount = $totalCount;
    }
}
