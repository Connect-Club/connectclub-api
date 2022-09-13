<?php

namespace App\Entity\Activity;

use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\Activity\ArrangedPrivateMeetingActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ArrangedPrivateMeetingActivityRepository::class)
 */
class ArrangedPrivateMeetingActivity extends Activity implements EventScheduleActivityInterface
{
    /** @ORM\ManyToOne(targetEntity="App\Entity\Event\EventSchedule") */
    public EventSchedule $eventSchedule;

    public function __construct(EventSchedule $eventSchedule, User $user, User ...$users)
    {
        $this->eventSchedule = $eventSchedule;

        parent::__construct($user, ...$users);
    }

    public function getType(): string
    {
        return self::TYPE_ARRANGED_PRIVATE_MEETING;
    }

    public function getEventSchedule(): EventSchedule
    {
        return $this->eventSchedule;
    }
}
