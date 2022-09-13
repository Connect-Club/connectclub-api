<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\VideoRoomObject;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\Object\VideoRoomSpeakerLocationObjectRepository")
 */
class VideoRoomSpeakerLocationObject extends VideoRoomObject
{
}
