<?php

namespace App\Entity\Club;

use App\Entity\User;
use App\Repository\Club\JoinRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table("club_join_request")
 * @ORM\Entity(repositoryClass=JoinRequestRepository::class)
 */
class JoinRequest
{
    const STATUS_MODERATION = 'moderation';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_APPROVED = 'approved';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Club\Club") */
    public Club $club;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $author;

    /** @ORM\Column(type="string") */
    public string $status = self::STATUS_MODERATION;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public ?User $handledBy;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $handledAt;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(Club $club, User $author)
    {
        $this->id = Uuid::uuid4();
        $this->club = $club;
        $this->author = $author;
        $this->createdAt = time();
    }

    public function approveBy(User $approveBy): self
    {
        $this->status = self::STATUS_APPROVED;
        $this->handledBy = $approveBy;
        $this->handledAt = time();

        return $this;
    }

    public function cancelBy(User $cancelledBy): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->handledBy = $cancelledBy;
        $this->handledAt = time();

        return $this;
    }
}
