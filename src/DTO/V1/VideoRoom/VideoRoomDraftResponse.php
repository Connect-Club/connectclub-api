<?php

namespace App\DTO\V1\VideoRoom;

class VideoRoomDraftResponse
{
    /** @var int */
    public int $id;
    /** @var string */
    public string $description;
    /** @var int */
    public int $backgroundRoomId;
    /** @var int */
    public int $backGroundRoomWidthMultiplier;
    /** @var int */
    public int $backGroundRoomHeightMultiplier;
    /** @var string */
    public string $getResizerUrl;
    /** @var int */
    public int $index;
    /** @var string */
    public string $type;

    public function __construct(
        int $id,
        string $description,
        int $backgroundRoomId,
        int $backGroundRoomWidthMultiplier,
        int $backGroundRoomHeightMultiplier,
        string $getResizerUrl,
        int $index,
        string $type
    ) {
        $this->id = $id;
        $this->description = $description;
        $this->backgroundRoomId = $backgroundRoomId;
        $this->backGroundRoomWidthMultiplier = $backGroundRoomWidthMultiplier;
        $this->backGroundRoomHeightMultiplier = $backGroundRoomHeightMultiplier;
        $this->getResizerUrl = $getResizerUrl;
        $this->index = $index;
        $this->type = $type;
    }
}
