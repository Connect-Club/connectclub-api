<?php

namespace App\DTO\V1\Event;

use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V2\Interests\InterestDTO;
use App\Entity\Event\EventDraft;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Interest\Interest;
use App\Entity\VideoChat\VideoRoom;

class OnlineEventItem
{
    public string $id;

    /** @var ?string */
    public ?string $title;

    /** @var OnlineEventUserInfo[] */
    public array $participants;

    /** @var OnlineEventUserInfo[] */
    public array $speakers = [];

    /** @var OnlineEventUserInfo[] */
    public array $listeners = [];

    /** @var bool */
    public bool $isCoHost = false;

    /** @var InterestDTO[] */
    public array $interests = [];

    /** @var int */
    public int $online;

    /** @var int */
    public int $speaking;

    /** @var string */
    public string $roomId;

    /** @var string */
    public string $roomPass;

    /** @var bool */
    public bool $withSpeakers;

    /** @var bool */
    public bool $isPrivate;

    /** @var string */
    public string $draftType;

    public ?string $subscriptionId;

    /** @var string|null */
    public ?string $eventScheduleId;

    public ?ClubSlimResponse $club = null;

    public function __construct(
        ?string $title,
        array $firstParticipants,
        int $participantsTotalCount,
        int $speakersTotalCount,
        VideoRoom $videoRoom,
        bool $isCoHost = false,
        ?array $interests = null
    ) {
        $this->id = (string) $videoRoom->id;
        $this->title = $title;
        $this->participants = $firstParticipants;
        $this->online = $participantsTotalCount;
        $this->speaking = $speakersTotalCount;
        $this->roomId = $videoRoom->community->name;
        $this->roomPass = $videoRoom->community->password;
        $this->withSpeakers = $videoRoom->config->withSpeakers;
        $this->isPrivate = $videoRoom->isPrivate;
        $this->isCoHost = $isCoHost;
        $this->subscriptionId = $videoRoom->subscription && $videoRoom->subscription->isActive ?
            $videoRoom->subscription->id->toString() : '';

        if ($interests === null && $videoRoom->eventSchedule) {
            $interests = $videoRoom->eventSchedule->interests->map(
                fn(EventScheduleInterest $i) => $i->interest
            )->toArray();
        }

        if ($interests) {
            $this->interests = array_map(
                fn(Interest $interest) => new InterestDTO($interest),
                $interests
            );
        }

        if ($language = $videoRoom->language) {
            $this->interests = array_merge(
                [InterestDTO::createFromFields($language->id, $language->name, true)],
                $this->interests
            );
        }

        $this->draftType = $videoRoom->draftType ?? EventDraft::TYPE_SMALL_BROADCASTING;
    }
}
