<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\Object\VideoRoomVideoObject;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomObjectVideoDataResponse
{
    /**
     * @var float
     * @Groups({"default"})
     */
    public float $radius;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $videoSrc;

    /**
     * @var integer
     * @Groups({"default"})
     */
    public int $length;

    public function __construct(VideoRoomVideoObject $videoObject)
    {
        $this->radius = $videoObject->radius;
        $this->videoSrc = $videoObject->videoSrc;
        $this->length = $videoObject->length;
    }
}
