<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\Photo\NftImage;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use App\Repository\VideoChat\Object\VideoRoomNftImageObjectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VideoRoomNftImageObjectRepository::class)
 */
class VideoRoomNftImageObject extends VideoRoomObject
{
    /** @ORM\ManyToOne(targetEntity="App\Entity\Photo\NftImage") */
    public NftImage $photo;

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
