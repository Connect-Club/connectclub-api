<?php

namespace App\DTO\V1\Event;

use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V2\Interests\InterestDTO;
use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Interest\Interest;

class EventFestivalResponse
{
    /** @var string */
    public string $id;

    /** @var string */
    public string $title;

    /** @var string|null */
    public ?string $description;

    /** @var int */
    public int $date;

    /** @var int */
    public int $dateEnd;

    /** @var string */
    public string $festivalCode;

    /** @var string */
    public string $festivalSceneId;

    /** @var EventFestivalSceneResponse|null */
    public ?EventFestivalSceneResponse $festivalScene = null;

    public ?ClubSlimResponse $club = null;

    /** @var UserInfoResponse[] */
    public array $participants;

    /** @var InterestDTO[] */
    public array $interests = [];

    public function __construct(
        EventSchedule $eventSchedule
    ) {
        $this->id = $eventSchedule->id->toString();
        $this->festivalSceneId = $eventSchedule->festivalScene ?
                                 $eventSchedule->festivalScene->id->toString() :
                                 '';

        $this->festivalScene = $eventSchedule->festivalScene ?
                               new EventFestivalSceneResponse($eventSchedule->festivalScene) :
                               null;

        $this->festivalCode = (string) $eventSchedule->festivalCode;
        $this->title = $eventSchedule->name;
        $this->date = $eventSchedule->dateTime;
        $this->dateEnd = (int) $eventSchedule->endDateTime;
        $this->description = $eventSchedule->description;
        $this->participants = $eventSchedule->participants->map(
            fn(EventScheduleParticipant $p) => new EventScheduleParticipantResponse(
                $p->user,
                $p->user->equals($eventSchedule->owner)
            )
        )->getValues();
        $this->interests = array_map(
            fn(Interest $i) => new InterestDTO($i),
            $eventSchedule->interests->map(fn(EventScheduleInterest $i) => $i->interest)->toArray()
        );

        if ($eventSchedule->club) {
            $this->club = new ClubSlimResponse($eventSchedule->club);
        }
    }
}
