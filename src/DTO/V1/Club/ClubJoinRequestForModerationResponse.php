<?php

namespace App\DTO\V1\Club;

use App\DTO\V2\User\FullUserInfoResponse;
use App\Entity\Club\JoinRequest;

class ClubJoinRequestForModerationResponse
{
    /** @var string */
    public string $joinRequestId;

    /** @var FullUserInfoResponse */
    public FullUserInfoResponse $user;

    public function __construct(JoinRequest $joinRequest)
    {
        $this->joinRequestId = $joinRequest->id->toString();
        //@todo fix isFollowing, isFollows, follow, following
        $this->user = new FullUserInfoResponse($joinRequest->author, false, false, 0, 0);
    }
}
