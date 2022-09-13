<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\Photo\VideoRoomImageObjectPhoto;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use App\Repository\VideoChat\Object\VideoRoomImageObjectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VideoRoomImageObjectRepository::class)
 */
class VideoRoomImageObject extends VideoRoomObject
{
    /** @ORM\ManyToOne(targetEntity="App\Entity\Photo\VideoRoomImageObjectPhoto") */
    public VideoRoomImageObjectPhoto $photo;

    /** @ORM\Column(type="string", nullable=true) */
    public ?string $title = null;

    /** @ORM\Column(type="text", nullable=true) */
    public ?string $description = null;

    public function __construct(
        ?VideoRoom $videoRoom,
        ?BackgroundPhoto $background,
        Location $location,
        int $width,
        int $height
    ) {
        parent::__construct($videoRoom, $background, $location, $width, $height);
    }
}
