<?php

namespace App\DTO\V1\VideoRoom;

class UpdateVideoRoomConfigRequest
{
    /** @var int|null */
    public ?int $backgroundPhotoId = null;

    /** @var string|null */
    public ?string $description = null;
}
