<?php

namespace App\Entity\Notification;

use App\Entity\User;
use App\Repository\Notification\NotificationStatisticRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=NotificationStatisticRepository::class)
 */
class NotificationStatistic
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\Column(type="string") */
    public string $code;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $clickedBy;

    /** @ORM\Column(type="integer") */
    public int $createdAt;

    public function __construct(User $clickedBy, string $code)
    {
        $this->id = Uuid::uuid4();
        $this->code = $code;
        $this->clickedBy = $clickedBy;
        $this->createdAt = time();
    }
}
