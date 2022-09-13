<?php

namespace App\Entity\VideoChat;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Embeddable()
 */
class VideoRoomQuality
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"default"})
     */
    public int $width;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups({"default"})
     */
    public int $height;

    /**
     * Room quality constructor.
     */
    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
    }
}
