<?php

namespace App\Entity\Event;

use App\Entity\User;
use App\Repository\Event\EventScheduleSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EventScheduleSubscriptionRepository::class)
 */
class EventScheduleSubscription
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Event\EventSchedule") */
    public EventSchedule $eventSchedule;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $user;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $notificationHourlySendAt = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $notificationDailySendAt = null;

    /** @ORM\Column(type="bigint", nullable=true) */
    public ?int $notificationSendAt = null;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(EventSchedule $eventSchedule, User $user)
    {
        $this->id = Uuid::uuid4();
        $this->eventSchedule = $eventSchedule;
        $this->user = $user;
        $this->createdAt = time();
    }
}
