<?php

namespace App\Entity\Photo;

use App\Entity\VideoChat\Object\VideoRoomImageObject;
use App\Repository\Photo\VideoRoomImageObjectPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VideoRoomImageObjectPhotoRepository::class)
 */
class VideoRoomImageObjectPhoto extends AbstractPhoto
{
}
