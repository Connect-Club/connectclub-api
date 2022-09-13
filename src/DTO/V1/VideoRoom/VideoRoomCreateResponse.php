<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\VideoRoom;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomCreateResponse extends VideoRoomResponse
{
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $password;

    public function __construct(VideoRoom $room)
    {
        parent::__construct($room);

        $this->password = $room->community->password;
    }
}
