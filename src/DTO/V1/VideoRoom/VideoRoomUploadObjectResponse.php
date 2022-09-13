<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\VideoRoomObject;

class VideoRoomUploadObjectResponse
{
    /** @var int */
    public int $id;

    /** @var string */
    public string $type;

    public function __construct(VideoRoomObject $videoRoomObject, string $type)
    {
        $this->id = $videoRoomObject->id;
        $this->type = $type;
    }
}
