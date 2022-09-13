<?php

namespace App\Entity\VideoChat;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VideoChat\VideoRoomHistoryRepository")
 */
class VideoRoomHistory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom") */
    public VideoRoom $videoRoom;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $user;

    /** @ORM\Column(type="string") */
    public string $password;

    /** @ORM\Column(type="integer") */
    public int $joinedAt;

    public function __construct(VideoRoom $videoRoom, User $user, int $joinedAt)
    {
        $this->videoRoom = $videoRoom;
        $this->password = $videoRoom->community->password;
        $this->user = $user;
        $this->joinedAt = $joinedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
