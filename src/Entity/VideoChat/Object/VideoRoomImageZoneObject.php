<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\VideoRoomObject;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\VideoChat\Object\VideoRoomImageZoneObjectRepository;

/**
 * @ORM\Entity(repositoryClass=VideoRoomImageZoneObjectRepository::class)
 */
class VideoRoomImageZoneObject extends VideoRoomObject
{

}
