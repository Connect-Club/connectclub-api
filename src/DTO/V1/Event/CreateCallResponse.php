<?php

namespace App\DTO\V1\Event;

use App\Entity\VideoChat\VideoRoom;

class CreateCallResponse
{
    public string $inviteId;
    public string $message;

    public function __construct(VideoRoom $room, string $message)
    {
        $this->inviteId = $room->community->name;
        $this->message = $message;
    }
}
