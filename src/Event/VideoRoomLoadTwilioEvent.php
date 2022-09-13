<?php

namespace App\Event;

use App\Entity\VideoChat\VideoRoom;
use Symfony\Contracts\EventDispatcher\Event;

class VideoRoomLoadTwilioEvent extends Event
{
    public VideoRoom $videoRoom;
    public string $sid;

    public function __construct(VideoRoom $videoRoom)
    {
        $this->videoRoom = $videoRoom;
    }
}
