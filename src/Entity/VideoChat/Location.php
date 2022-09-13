<?php

namespace App\Entity\VideoChat;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Embeddable()
 */
class Location
{
    /**
     * @ORM\Column(type="integer", options={"default": 0})
     * @Groups({"default"})
     */
    public int $x = 0;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     * @Groups({"default"})
     */
    public int $y = 0;

    public function __construct(int $x = 0, int $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }
}
