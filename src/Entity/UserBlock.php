<?php

namespace App\Entity;

use App\Repository\UserBlockRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=UserBlockRepository::class)
 */
class UserBlock
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $author;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $blockedUser;

    /** @ORM\Column(type="boolean") */
    public bool $isWasFollowing = false;

    /** @ORM\Column(type="boolean") */
    public bool $isWasFollows = false;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $deletedAt = null;

    public function __construct(
        User $author,
        User $blockedUser,
        bool $isWasFollowing = false,
        bool $isWasFollows = false
    ) {
        $this->id = Uuid::uuid4();
        $this->author = $author;
        $this->blockedUser = $blockedUser;
        $this->isWasFollowing = $isWasFollowing;
        $this->isWasFollows = $isWasFollows;
        $this->createdAt = time();
    }
}
