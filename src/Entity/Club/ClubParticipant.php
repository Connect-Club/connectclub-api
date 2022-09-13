<?php

namespace App\Entity\Club;

use App\Entity\User;
use App\Repository\Club\ClubParticipantRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=ClubParticipantRepository::class)
 */
class ClubParticipant
{
    const ROLE_MEMBER = 'member';
    const ROLE_MODERATOR = 'moderator';
    const ROLE_OWNER = 'owner';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="clubParticipants") */
    public User $user;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Club\Club", inversedBy="participants") */
    public Club $club;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $joinedBy;

    /** @ORM\Column(type="string") */
    public string $role;

    /** @ORM\Column(type="bigint") */
    public int $joinedAt;

    public function __construct(Club $club, User $user, User $joinedBy, string $role = self::ROLE_MEMBER)
    {
        $this->id = Uuid::uuid4();
        $this->user = $user;
        $this->club = $club;
        $this->joinedBy = $joinedBy;
        $this->role = $role;
        $this->joinedAt = time();
    }
}
