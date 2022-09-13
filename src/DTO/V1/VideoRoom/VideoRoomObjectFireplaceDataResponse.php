<?php

namespace App\DTO\V1\VideoRoom;

use App\Entity\VideoChat\Object\VideoRoomFireplaceObject;
use App\Entity\VideoChat\VideoRoomObject;
use Symfony\Component\Serializer\Annotation\Groups;

class VideoRoomObjectFireplaceDataResponse
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
    public string $lottieSrc;

    /**
     * @var string
     * @Groups({"default"})
     */
    public string $soundSrc;

    public function __construct(float $radius, string $lottieSrc, string $soundSrc)
    {
        $this->radius = $radius;
        $this->lottieSrc = $lottieSrc;
        $this->soundSrc = $soundSrc;
    }

    public static function createFromObject(
        VideoRoomFireplaceObject $videoRoomObject
    ): VideoRoomObjectFireplaceDataResponse {
        return new self($videoRoomObject->radius, $videoRoomObject->lottieSrc, $videoRoomObject->soundSrc);
    }
}
