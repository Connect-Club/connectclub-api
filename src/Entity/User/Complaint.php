<?php

namespace App\Entity\User;

use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\User\ComplaintRepository")
 */
class Complaint
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    public User $author;

    /**
     * @var VideoRoom|null
     * @ORM\JoinColumn(nullable=true)
     * @ORM\ManyToOne(targetEntity="App\Entity\VideoChat\VideoRoom")
     */
    public ?VideoRoom $videoRoom = null;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    public User $abuser;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    public ?string $reason;

    /**
     * @var string|null
     * @ORM\Column(type="text", nullable=true)
     */
    public ?string $description;

    public function __construct(User $author, ?VideoRoom $videoRoom, User $abuser, ?string $reason)
    {
        $this->author = $author;
        $this->videoRoom = $videoRoom;
        $this->abuser = $abuser;
        $this->reason = $reason;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
