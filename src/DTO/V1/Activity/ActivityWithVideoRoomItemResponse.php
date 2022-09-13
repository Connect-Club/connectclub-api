<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\Activity;
use App\Entity\Activity\ActivityWithVideoRoomInterface;

class ActivityWithVideoRoomItemResponse extends ActivityItemResponse
{
    public string $roomId;
    public string $roomPass;

    public function __construct(ActivityWithVideoRoomInterface $activity, string $title)
    {
        parent::__construct($activity, $title);

        $this->roomId = $activity->getVideoRoom()->community->name;
        $this->roomPass = $activity->getVideoRoom()->community->password;
    }
}
