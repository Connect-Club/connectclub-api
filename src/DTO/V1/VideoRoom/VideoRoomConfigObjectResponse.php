<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\VideoRoomObject;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomConfigObjectResponse
{
    /**
     * @var string
     * @Groups({"default"})
     */
    public string $type;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $x;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $y;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $width;

    /**
     * @var int
     * @Groups({"default"})
     */
    public int $height;

    public function __construct(string $type, int $x, int $y, int $width, int $height)
    {
        $this->type = $type;
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
    }

    public static function createFromObject(
        string $type,
        VideoRoomObject $videoRoomObject
    ): VideoRoomConfigObjectResponse {
        return new self(
            $type,
            $videoRoomObject->location->x,
            $videoRoomObject->location->y,
            $videoRoomObject->width,
            $videoRoomObject->height
        );
    }
}
