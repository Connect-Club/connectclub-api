<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\Object\VideoRoomVideoObjectRepository")
 */
class VideoRoomVideoObject extends VideoRoomObject
{
    /** @ORM\Column(type="float") */
    public float $radius;

    /** @ORM\Column(type="string") */
    public string $videoSrc;

    /** @ORM\Column(type="integer") */
    public int $length;

    public function __construct(
        ?VideoRoom $videoRoom,
        ?BackgroundPhoto $background,
        Location $location,
        int $width,
        int $height,
        float $radius,
        string $videoSrc,
        int $length
    ) {
        $this->radius = $radius;
        $this->videoSrc = $videoSrc;
        $this->length = $length;

        parent::__construct($videoRoom, $background, $location, $width, $height);
    }
}
