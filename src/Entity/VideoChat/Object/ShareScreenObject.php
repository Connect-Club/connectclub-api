<?php

namespace App\Entity\VideoChat\Object;

use App\Entity\VideoChat\VideoRoomObject;
use App\Repository\VideoChat\Object\ShareScreenObjectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ShareScreenObjectRepository::class)
 */
class ShareScreenObject extends VideoRoomObject
{
}
