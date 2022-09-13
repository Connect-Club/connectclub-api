<?php

namespace App\Entity\Community;

use App\Entity\User;
use App\Repository\Community\CommunityParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CommunityParticipantRepository::class)
 */
class CommunityParticipant
{
    const ROLE_MEMBER = 'member';
    const ROLE_ADMIN = 'admin';
    const ROLE_SPECIAL_GUESTS = 'special_guests';
    const ROLE_MODERATOR = 'moderator';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\Column(type="integer")
     */
    public ?int $id = null;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="joinedCommunities") */
    public User $user;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Community\Community", inversedBy="participants") */
    public Community $community;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /** @ORM\Column(type="bigint", options={"default": 0}) */
    public int $communityLastView = 0;

    /** @ORM\Column(type="string", options={"default": CommunityParticipant::ROLE_MEMBER}) */
    public string $role;

    public function __construct(User $user, Community $community, string $role = self::ROLE_MEMBER)
    {
        $this->user = $user;
        $this->community = $community;
        $this->communityLastView = (int) (microtime(true) * 10000);
        $this->role = $role;
        $this->createdAt = time();
    }
}
