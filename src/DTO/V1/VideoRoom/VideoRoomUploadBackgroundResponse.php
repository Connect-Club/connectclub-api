<?php

namespace App\DTO\V1\VideoRoom;

class VideoRoomUploadBackgroundResponse
{
    /** @var string */
    public string $src;

    public function __construct(string $src)
    {
        $this->src = $src;
    }
}
