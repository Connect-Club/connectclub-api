<?php

namespace App\Event;

class VideoRoomEventParameters
{
    public string $roomSid;
    public ?string $participantIdentity;
    public string $roomName;

    public function __construct(string $roomSid, ?string $participantIdentity, string $roomName)
    {
        $this->roomSid = $roomSid;
        $this->participantIdentity = $participantIdentity;
        $this->roomName = $roomName;
    }
}
