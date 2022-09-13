<?php

namespace App\DTO\V2\User;

class MutualFriendsPaginatedResponse
{
    /**
     * @var UserInfoWithFollowingData[]
     */
    public array $items = [];

    public ?int $lastValue;

    public int $totalCount;

    public function __construct(array $items, ?int $lastValue, int $totalCount)
    {
        $this->totalCount = $totalCount;
        $this->items = [];
        foreach ($items as $mutualFriend) {
            $this->items[] = new UserInfoWithFollowingData($mutualFriend, true, true);
        }
        $this->lastValue = $lastValue;
    }
}
