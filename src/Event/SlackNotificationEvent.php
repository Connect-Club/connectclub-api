<?php

namespace App\Event;

use App\Entity\VideoChat\VideoMeeting;

class SlackNotificationEvent
{
    private VideoMeeting $videoMeeting;

    public function __construct(VideoMeeting $videoMeeting)
    {
        $this->videoMeeting = $videoMeeting;
    }

    public function getVideoMeeting(): VideoMeeting
    {
        return $this->videoMeeting;
    }
}
