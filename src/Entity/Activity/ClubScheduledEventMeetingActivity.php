<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Activity\ClubScheduledEventMeetingActivityRepository;

/**
 * @ORM\Entity(repositoryClass=ClubScheduledEventMeetingActivityRepository::class)
 */
class ClubScheduledEventMeetingActivity extends Activity implements
    ClubActivityInterface,
    EventScheduleActivityInterface
{
    /** @ORM\ManyToOne(targetEntity=EventSchedule::class) */
    private EventSchedule $eventSchedule;

    /** @ORM\ManyToOne(targetEntity=Club::class) */
    private Club $club;

    public function __construct(Club $club, EventSchedule $eventSchedule, User $user, User ...$users)
    {
        $this->eventSchedule = $eventSchedule;
        $this->club = $club;

        parent::__construct($user, ...$users);
    }

    public function getClub(): Club
    {
        return $this->club;
    }

    public function getType(): string
    {
        return self::TYPE_USER_CLUB_SCHEDULE_EVENT;
    }

    public function getEventSchedule(): EventSchedule
    {
        return $this->eventSchedule;
    }
}
