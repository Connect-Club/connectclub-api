<?php

namespace App\DTO\V2\User;

class CountersResponse
{
    public int $connectingCount;
    public int $connectedCount;
    public int $mutualFriendsCount;

    public function __construct(int $connectingCount, int $connectedCount, int $mutualFriendsCount)
    {
        $this->connectingCount = $connectingCount;
        $this->connectedCount = $connectedCount;
        $this->mutualFriendsCount = $mutualFriendsCount;
    }
}
