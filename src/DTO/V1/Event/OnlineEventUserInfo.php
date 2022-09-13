<?php

namespace App\DTO\V1\Event;

use App\DTO\V2\User\UserInfoResponse;
use App\Entity\User;

class OnlineEventUserInfo extends UserInfoResponse
{
    /** @var bool */
    public bool $isSpeaker;

    /** @var bool */
    public bool $isSpecialGuest;

    public function __construct(bool $isSpeaker, User $user, bool $isSpecialGuest = false)
    {
        $this->isSpeaker = $isSpeaker;
        $this->isSpecialGuest = $isSpecialGuest;

        parent::__construct($user);
    }
}
