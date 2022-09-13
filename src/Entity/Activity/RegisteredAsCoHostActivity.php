<?php

namespace App\Entity\Activity;

use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\Activity\RegisteredAsCoHostActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RegisteredAsCoHostActivityRepository::class)
 */
class RegisteredAsCoHostActivity extends Activity implements EventScheduleActivityInterface
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
        return self::TYPE_REGISTERED_AS_CO_HOST;
    }

    public function getEventSchedule(): EventSchedule
    {
        return $this->eventSchedule;
    }
}
