<?php

namespace App\Entity\Event;

use App\Entity\Interest\Interest;
use App\Repository\Event\EventScheduleInterestRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass=EventScheduleInterestRepository::class)
 */
class EventScheduleInterest
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="uuid")
     */
    public UuidInterface $id;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Event\EventSchedule", inversedBy="interests") */
    public EventSchedule $eventSchedule;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Interest\Interest") */
    public Interest $interest;

    public function __construct(EventSchedule $eventSchedule, Interest $interest)
    {
        $this->id = Uuid::uuid4();
        $this->eventSchedule = $eventSchedule;
        $this->interest = $interest;
    }
}
