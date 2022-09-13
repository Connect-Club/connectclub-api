<?php

namespace App\Entity\Activity;

use App\Entity\Club\Club;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\Activity\ClubRegisteredAsCoHostActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClubRegisteredAsCoHostActivityRepository::class)
 */
class ClubRegisteredAsCoHostActivity extends Activity implements
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
        return self::TYPE_USER_CLUB_SCHEDULE_REGISTERED_AS_CO_HOST;
    }

    public function getEventSchedule(): EventSchedule
    {
        return $this->eventSchedule;
    }
}
