<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\Object\VideoRoomFireplaceObject;
use App\Entity\VideoChat\VideoRoomObject;
use App\Entity\VideoChatObject\QuietZoneObject;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomObjectQuietZoneDataResponse
{
    /**
     * @var float
     * @Groups({"default"})
     */
    public float $radius;

    public function __construct(float $radius)
    {
        $this->radius = $radius;
    }

    public static function createFromObject(
        QuietZoneObject $videoRoomObject
    ): VideoRoomObjectQuietZoneDataResponse {
        return new self($videoRoomObject->radius);
    }
}
