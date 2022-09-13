<?php

namespace App\DTO\V1\VideoRoom;

class CreateComplaintRequest
{
    /** @var int */
    public int $abuserId;

    /** @var string|null */
    public ?string $videoRoomName = null;

    /** @var string|null */
    public ?string $reason;
}
