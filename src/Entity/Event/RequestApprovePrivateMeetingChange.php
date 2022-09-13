<?php

namespace App\Entity\Event;

use App\Entity\User;
use App\Repository\Event\RequestApprovePrivateMeetingChangeRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=RequestApprovePrivateMeetingChangeRepository::class)
 */
class RequestApprovePrivateMeetingChange
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

    /** @ORM\Column(type="boolean") */
    public bool $reviewed = false;

    /** @ORM\Column(type="bigint") */
    public int $createdAt;

    /**
     * @param EventSchedule $eventSchedule
     * @param User $user
     */
    public function __construct(EventSchedule $eventSchedule, User $user)
    {
        $this->id = Uuid::uuid4();
        $this->eventSchedule = $eventSchedule;
        $this->user = $user;
        $this->createdAt = time();
    }
}
