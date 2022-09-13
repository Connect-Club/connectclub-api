<?php

namespace App\Entity\Club;

use App\Entity\User;
use App\Repository\Club\ClubInviteRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=ClubInviteRepository::class)
 */
class ClubInvite
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Club\Club")
     */
    public Club $club;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    public User $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    public User $createdBy;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public ?int $notificationSendAt = null;

    /**
     * @ORM\Column(type="bigint")
     */
    public int $createdAt;

    public function __construct(Club $club, User $user, User $createdBy)
    {
        $this->id = Uuid::uuid4();
        $this->club = $club;
        $this->user = $user;
        $this->createdBy = $createdBy;
        $this->createdAt = time();
    }
}
