<?php

namespace App\Entity\Activity;

use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\Activity\ScheduledEventMeetingActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ScheduledEventMeetingActivityRepository::class)
 */
class ScheduledEventMeetingActivity extends Activity implements EventScheduleActivityInterface
{
    /** @ORM\ManyToOne(targetEntity="App\Entity\Event\EventSchedule") */
    private EventSchedule $eventSchedule;

    public function __construct(EventSchedule $eventSchedule, User $user, User ...$users)
    {
        $this->eventSchedule = $eventSchedule;

        parent::__construct($user, ...$users);
    }

    public function getType(): string
    {
        return self::TYPE_USER_SCHEDULE_EVENT;
    }

    public function getEventSchedule(): EventSchedule
    {
        return $this->eventSchedule;
    }
}
