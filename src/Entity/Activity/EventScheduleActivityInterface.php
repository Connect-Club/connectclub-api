<?php

namespace App\Entity\Activity;

use App\Entity\Event\EventSchedule;

interface EventScheduleActivityInterface extends ActivityInterface
{
    public function getEventSchedule(): EventSchedule;
}
