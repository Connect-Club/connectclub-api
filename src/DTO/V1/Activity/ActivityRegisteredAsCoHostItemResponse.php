<?php

namespace App\DTO\V1\Activity;

use App\Entity\Activity\RegisteredAsCoHostActivity;

class ActivityRegisteredAsCoHostItemResponse extends ActivityItemResponse
{
    /** @var string */
    public string $eventScheduleId;

    /** @var int */
    public int $date;

    public function __construct(RegisteredAsCoHostActivity $activity, string $title)
    {
        parent::__construct($activity, $title);

        $this->eventScheduleId = $activity->getEventSchedule()->id->toString();
        $this->date = $activity->getEventSchedule()->dateTime;
    }
}
