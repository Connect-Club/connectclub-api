<?php

namespace App\Entity\VideoRoom;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\VideoRoom\ScreenShareTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=ScreenShareTokenRepository::class)
 */
class ScreenShareToken
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string", unique=true, nullable=true) */
    public string $token;

    /** @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom") */
    public VideoRoom $videoRoom;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $user;

    /** @ORM\Column(type="integer") */
    public int $createdAt;

    public function __construct(VideoRoom $videoRoom, User $user, string $token)
    {
        $this->id = Uuid::uuid4();
        $this->videoRoom = $videoRoom;
        $this->user = $user;
        $this->token = $token;
        $this->createdAt = time();
    }
}
