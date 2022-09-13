<?php

namespace App\Entity\Event;

use App\Entity\User;
use App\Repository\Event\EventScheduleParticipantRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EventScheduleParticipantRepository::class)
 */
class EventScheduleParticipant
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Event\EventSchedule", inversedBy="participants") */
    public EventSchedule $event;

    /** @ORM\ManyToOne(targetEntity="App\Entity\User") */
    public User $user;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    public bool $isSpecialGuest = false;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    public function __construct(EventSchedule $event, User $user, bool $isSpecialGuest = false)
    {
        $this->id = Uuid::uuid4();
        $this->event = $event;
        $this->user = $user;
        $this->isSpecialGuest = $isSpecialGuest;
        $this->createdAt = time();
    }
}
