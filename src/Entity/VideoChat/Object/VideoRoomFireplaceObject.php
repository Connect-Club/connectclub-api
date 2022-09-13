<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\Object\VideoRoomFireplaceObjectRepository")
 */
class VideoRoomFireplaceObject extends VideoRoomObject
{
    /** @ORM\Column(type="float") */
    public float $radius;

    /** @ORM\Column(type="string") */
    public string $lottieSrc;

    /** @ORM\Column(type="string") */
    public string $soundSrc;

    public function __construct(
        ?VideoRoom $videoRoom,
        ?BackgroundPhoto $background,
        Location $location,
        int $width,
        int $height,
        float $radius,
        string $lottieSrc,
        string $soundSrc
    ) {
        $this->radius = $radius;
        $this->lottieSrc = $lottieSrc;
        $this->soundSrc = $soundSrc;

        parent::__construct($videoRoom, $background, $location, $width, $height);
    }
}
