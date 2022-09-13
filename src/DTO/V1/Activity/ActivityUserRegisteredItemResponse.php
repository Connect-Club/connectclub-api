<?php

namespace App\DTO\V1\Activity;

use App\DTO\V2\User\UserInfoWithFollowingData;
use App\Entity\Activity\Activity;
use App\Entity\Activity\ActivityInterface;
use App\Entity\User;

class ActivityUserRegisteredItemResponse extends ActivityItemResponse
{
    /** @var UserInfoWithFollowingData[] */
    public array $relatedUsers;

    public function __construct(bool $isFollowing, bool $isFollows, ActivityInterface $activity, string $title)
    {
        parent::__construct($activity, $title);

        $this->relatedUsers = array_map(
            fn(User $u) => new UserInfoWithFollowingData($u, $isFollowing, $isFollows),
            $activity->getNestedUsers()->toArray()
        );
    }
}
