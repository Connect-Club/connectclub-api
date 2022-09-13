<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\Activity\RegisteredAsSpeakerActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RegisteredAsSpeakerActivityRepository::class)
 */
class RegisteredAsSpeakerActivity extends Activity implements EventScheduleActivityInterface
{
    /** @ORM\ManyToOne(targetEntity="App\Entity\Event\EventSchedule") */
    private EventSchedule $eventSchedule;

    /** @ORM\ManyToOne(targetEntity="App\Entity\Club\Club") */
    public ?Club $club = null;

    /** @ORM\Column(type="boolean", options={"default": 0}) */
    public bool $isForClub;

    public function __construct(EventSchedule $eventSchedule, ?Club $club, User $user, User ...$users)
    {
        $this->eventSchedule = $eventSchedule;
        $this->club = $club;
        $this->isForClub = $club !== null;

        parent::__construct($user, ...$users);
    }

    public function getType(): string
    {
        return self::TYPE_REGISTERED_AS_SPEAKER;
    }

    public function getEventSchedule(): EventSchedule
    {
        return $this->eventSchedule;
    }
}
