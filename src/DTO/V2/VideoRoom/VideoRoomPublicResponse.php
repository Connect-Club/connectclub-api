<?php

namespace App\DTO\V2\VideoRoom;

class VideoRoomPublicResponse
{
    public bool $withSpeakers = true;

    public function __construct(bool $withSpeakers = true)
    {
        $this->withSpeakers = $withSpeakers;
    }
}
