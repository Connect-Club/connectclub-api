<?php

namespace App\Entity\VideoChatObject;

use App\Entity\VideoChat\VideoRoomObject;
use App\Repository\VideoChatObject\QuietZoneObjectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=QuietZoneObjectRepository::class)
 */
class QuietZoneObject extends VideoRoomObject
{
    /** @ORM\Column(type="float") */
    public float $radius = 0;
}
