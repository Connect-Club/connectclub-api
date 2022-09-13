<?php

namespace App\DTO\V1\Subscription;

use App\DTO\V1\User\UserInfoResponse;
use App\DTO\V2\Interests\InterestDTO;
use App\DTO\V2\User\LanguageDTO;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Interest\Interest;
use App\Entity\User;

class Event
{
    public string $id;
    public ?string $title;
    public string $description;
    public int $date;
    public int $dateEnd;
    public ?LanguageDTO $language;

    /** @var UserInfoResponse[] */
    public array $participants;

    public int $listenerCount;

    /** @var LanguageDTO[] */
    public array $interests = [];

    public function __construct(EventSchedule $eventSchedule)
    {
        $this->id = $eventSchedule->id->toString();
        $this->title = $eventSchedule->name;
        $this->date = $eventSchedule->dateTime;
        $this->dateEnd = $eventSchedule->endDateTime;
        $this->description = $eventSchedule->description;

        $this->participants = $this->getParticipants($eventSchedule);

        $speakerCount = $this->getSpeakerCount($eventSchedule, $this->getPlannedSpeakers($eventSchedule));

        $this->listenerCount = count($this->participants) - $speakerCount;

        $this->interests = $this->sortInterests($eventSchedule);

        $this->language = $eventSchedule->language ? new LanguageDTO($eventSchedule->language) : null;
    }

    /**
     * @param User[] $plannedSpeakers
     */
    private function getSpeakerCount(EventSchedule $eventSchedule, array $plannedSpeakers): int
    {
        if (!$eventSchedule->videoRoom) {
            return 0;
        }

        $cameSpeakers = [];
        foreach ($eventSchedule->videoRoom->meetings as $meeting) {
            foreach ($meeting->participants as $participant) {
                $participantUserId = $participant->participant->id;

                if (isset($plannedSpeakers[$participantUserId])) {
                    $cameSpeakers[$participantUserId] = $participantUserId;
                }
            }
        }

        return count($cameSpeakers);
    }

    private function getParticipants(EventSchedule $eventSchedule): array
    {
        if (!$eventSchedule->videoRoom) {
            return [];
        }

        $participants = [];
        foreach ($eventSchedule->videoRoom->meetings as $meeting) {
            foreach ($meeting->participants as $participant) {
                $participants[$participant->participant->id] = new UserInfoResponse($participant->participant);
            }
        }

        return array_values($participants);
    }

    private function sortInterests(EventSchedule $eventSchedule): array
    {
        $interests = $eventSchedule->interests->map(
            fn(EventScheduleInterest $scheduleInterest) => $scheduleInterest->interest
        )->toArray();

        return array_map(
            fn(Interest $interest) => new InterestDTO($interest),
            $interests
        );
    }

    /**
     * @return User[]
     */
    private function getPlannedSpeakers(EventSchedule $eventSchedule): array
    {
        $speakers = [];
        foreach ($eventSchedule->participants as $participant) {
            $speakers[$participant->user->id] = $participant->user;
        }

        return $speakers;
    }
}
