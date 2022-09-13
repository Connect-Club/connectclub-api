<?php

namespace App\Entity\Activity;

use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\Activity\ApprovedPrivateMeetingActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ApprovedPrivateMeetingActivityRepository::class)
 */
class ApprovedPrivateMeetingActivity extends Activity implements EventScheduleActivityInterface
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
        return self::TYPE_APPROVED_PRIVATE_MEETING;
    }

    public function getEventSchedule(): EventSchedule
    {
        return $this->eventSchedule;
    }
}
