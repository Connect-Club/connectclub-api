<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\VideoRoomObject;
use App\Repository\VideoChat\Object\VideoRoomObjectTimeBoxRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VideoRoomObjectTimeBoxRepository::class)
 */
class VideoRoomObjectTimeBox extends VideoRoomObject
{

}
