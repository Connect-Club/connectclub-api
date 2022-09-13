<?php

namespace App\Entity\VideoChat;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\VideoRoomBanRepository")
 */
class VideoRoomBan
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom", inversedBy="bans") */
    public VideoRoom $videoRoom;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User")  */
    public User $abuser;

    public function __construct(VideoRoom $videoRoom, User $abuser)
    {
        $this->videoRoom = $videoRoom;
        $this->abuser = $abuser;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
