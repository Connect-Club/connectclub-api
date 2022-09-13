<?php

namespace App\Entity\Activity;

use App\Entity\VideoChat\VideoRoom;

interface ActivityWithVideoRoomInterface extends ActivityInterface
{
    public function getVideoRoom(): VideoRoom;
}
