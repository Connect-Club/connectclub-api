<?php

namespace App\DTO\V1\VideoRoom;

class VideoRoomPublicResponse
{
    public bool $withSpeakers = true;

    public function __construct(bool $withSpeakers = true)
    {
        $this->withSpeakers = $withSpeakers;
    }
}
