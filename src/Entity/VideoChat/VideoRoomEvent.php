<?php

namespace App\Entity\VideoChat;

use App\Entity\User;
use App\Repository\VideoChat\VideoRoomEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=VideoRoomEventRepository::class)
 */
class VideoRoomEvent
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    private UuidInterface $id;

    /** @ORM\Column(type="string") */
    private string $event;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom") */
    private VideoRoom $videoRoom;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    private User $user;

    /** @ORM\Column(type="bigint") */
    private int $time;

    public function __construct(VideoRoom $videoRoom, User $user, string $event)
    {
        $this->id = Uuid::uuid4();
        $this->event = $event;
        $this->videoRoom = $videoRoom;
        $this->user = $user;
        $this->time = (int) round(microtime(true) * 1000);
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getVideoRoom(): VideoRoom
    {
        return $this->videoRoom;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTime(): int
    {
        return $this->time;
    }
}
