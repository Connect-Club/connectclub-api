<?php

namespace App\DTO\V2\User;

class FriendInfoResponse extends UserInfoResponse
{
    public ?bool $alreadyInvitedToClub = null;
}
