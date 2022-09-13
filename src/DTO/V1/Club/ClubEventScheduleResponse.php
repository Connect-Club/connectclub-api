<?php

namespace App\DTO\V1\Club;

use App\DTO\V1\Event\EventFestivalSceneResponse;
use App\DTO\V1\Event\EventScheduleParticipantResponse;
use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;

class ClubEventScheduleResponse
{
    /** @var string */
    public string $id;

    /** @var string */
    public string $title;

    /** @var int */
    public int $date;

    /** @var string|null */
    public ?string $description;

    /** @var UserInfoResponse[] */
    public array $participants;

    /** @var EventFestivalSceneResponse|null */
    public ?EventFestivalSceneResponse $festivalScene = null;

    /** @var string|null */
    public ?string $festivalCode = null;

    /** @var int|null */
    public ?int $dateEnd = null;

    public function __construct(
        EventSchedule $eventSchedule
    ) {
        $this->id = $eventSchedule->id->toString();
        $this->title = $eventSchedule->name;
        $this->date = $eventSchedule->dateTime;
        $this->dateEnd = $eventSchedule->endDateTime;
        $this->description = $eventSchedule->description;

        $this->participants = $eventSchedule->participants->map(
            fn(EventScheduleParticipant $p) => new EventScheduleParticipantResponse(
                $p->user,
                $p->user->equals($eventSchedule->owner),
                $p->isSpecialGuest,
                null
            )
        )->getValues();

        $this->festivalCode = $eventSchedule->festivalCode;
        if ($eventSchedule->festivalScene) {
            $this->festivalScene = new EventFestivalSceneResponse($eventSchedule->festivalScene);
        }
    }
}
