<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\Object\VideoRoomPortalObjectRepository")
 */
class VideoRoomPortalObject extends VideoRoomObject
{
    /** @ORM\Column(type="string", nullable=true) */
    public ?string $name;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $password = null;

    public function __construct(
        ?VideoRoom $videoRoom,
        ?BackgroundPhoto $background,
        Location $location,
        int $width,
        int $height,
        ?string $name,
        ?string $password
    ) {
        $this->name = $name;
        $this->password = $password;

        parent::__construct($videoRoom, $background, $location, $width, $height);
    }
}
