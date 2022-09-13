<?php

namespace App\DTO\V1;

class InterfaceResponse
{
    public bool $hasNewInvites;
    public int $countNewActivities;
    public int $countFreeInvites;
    public int $countPendingInvites;
    public int $countOnlineFriends;
    public bool $showFestivalBanner = false;
    public string $checkInRoomId = '';
    public string $checkInRoomPass = '';
    public string $communityLink = '';
    public string $joinDiscordLink = '';

    public function __construct(
        bool $hasNewInvites,
        int $countNewActivities,
        int $countFreeInvites,
        int $countPendingInvites,
        int $countOnlineFriends
    ) {
        $this->hasNewInvites = $hasNewInvites;
        $this->countNewActivities = $countNewActivities;
        $this->countFreeInvites = $countFreeInvites;
        $this->countPendingInvites = $countPendingInvites;
        $this->countOnlineFriends = $countOnlineFriends;
    }
}
