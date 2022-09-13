<?php

namespace App\Entity\Follow;

use App\Entity\User;
use App\Repository\Follow\FollowRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Table(
 *     name="follow",
 *     uniqueConstraints={@UniqueConstraint(name="follower_id_user_id", columns={"follower_id", "user_id"})
 * })
 * @ORM\Entity(repositoryClass=FollowRepository::class)
 */
class Follow
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $follower;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="followers") */
    public User $user;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(User $follower, User $user)
    {
        $this->id = Uuid::uuid4();
        $this->follower = $follower;
        $this->user = $user;
        $this->createdAt = time();
    }
}
