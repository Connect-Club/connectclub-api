<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\Object\VideoRoomSquarePortalObjectRepository")
 */
class VideoRoomSquarePortalObject extends VideoRoomObject
{
    /** @ORM\Column(type="string", nullable=true) */
    public ?string $name;

    public function __construct(
        ?VideoRoom $videoRoom,
        ?BackgroundPhoto $background,
        Location $location,
        int $width,
        int $height,
        ?string $name
    ) {
        $this->name  = $name;

        parent::__construct($videoRoom, $background, $location, $width, $height);
    }
}
