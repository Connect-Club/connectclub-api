<?php

namespace App\DTO\V1\Event;

use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V2\Interests\InterestDTO;
use App\DTO\V2\User\LanguageDTO;
use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Interest\Interest;

class EventScheduleResponse
{
    const STATE_CREATE_LATER = 'create_later';
    const STATE_CREATE_VIDEO_ROOM = 'create_room';
    const STATE_JOIN = 'join';
    const STATE_EXPIRED = 'expired';
    const STATE_CHECK_LATER = 'check_later';

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

    /** @var bool */
    public bool $isAlreadySubscribedToAllParticipants;

    /** @var bool */
    public bool $isSubscribed = false;

    /** @var bool */
    public bool $isOwned;

    /** @var string */
    public string $state;

    /** @var string|null */
    public ?string $roomId = null;

    /** @var string|null */
    public ?string $roomPass = null;

    /** @var InterestDTO[] */
    public array $interests = [];

    /** @var LanguageDTO|null */
    public ?LanguageDTO $language = null;

    /** @var EventFestivalSceneResponse|null */
    public ?EventFestivalSceneResponse $festivalScene = null;

    /** @var string|null */
    public ?string $festivalCode = null;

    /** @var int|null */
    public ?int $dateEnd = null;

    /** @var bool */
    public bool $forMembersOnly;

    /** @var bool */
    public bool $withToken = false;

    /** @var bool */
    public bool $isPrivate = false;

    private ?ClubSlimResponse $club = null;

    /** @var bool */
    public bool $needApprove = false;

    public function __construct(
        EventSchedule $eventSchedule,
        bool $isAlreadySubscribedToAllParticipants,
        bool $isOwned,
        ?array $interests = null,
        bool $isSubscribed = false,
        array $predefinedClubRoleParticipantInformation = []
    ) {
        $this->id = $eventSchedule->id->toString();
        $this->title = $eventSchedule->name;
        $this->date = $eventSchedule->dateTime;
        $this->dateEnd = $eventSchedule->endDateTime;
        $this->description = $eventSchedule->description;
        $this->isPrivate = $eventSchedule->isPrivate;
        $this->participants = $eventSchedule->participants->map(
            fn(EventScheduleParticipant $p) => new EventScheduleParticipantResponse(
                $p->user,
                $p->user->equals($eventSchedule->owner),
                $p->isSpecialGuest,
                $predefinedClubRoleParticipantInformation[$p->user->id] ?? null
            )
        )->getValues();

        usort(
            $this->participants,
            function (EventScheduleParticipantResponse $a, EventScheduleParticipantResponse $b) {
                $calc = function (EventScheduleParticipantResponse $u): int {
                    $balls = 0;

                    if ($u->isSpecialGuest) {
                        $balls += 5;
                    } elseif ($u->clubRole == ClubParticipant::ROLE_OWNER) {
                        $balls += 4;
                    } elseif ($u->clubRole == ClubParticipant::ROLE_MODERATOR) {
                        $balls += 3;
                    }

                    return $balls;
                };

                $a = $calc($a);
                $b = $calc($b);

                if ($a == $b) {
                    return 0;
                }

                return ($a < $b) ? 1 : -1;
            }
        );

        $this->isAlreadySubscribedToAllParticipants = $isAlreadySubscribedToAllParticipants; //@todo remove
        $this->isSubscribed = $isSubscribed;
        $this->isOwned = $isOwned;

        if ($eventSchedule->videoRoom && $eventSchedule->videoRoom->doneAt !== null
            && !$eventSchedule->videoRoom->alwaysReopen
        ) {
            $state = self::STATE_EXPIRED;
        } elseif ($eventSchedule->videoRoom) {
            if (($eventSchedule->videoRoom->startedAt !== null && !$eventSchedule->videoRoom->isPrivate) || $isOwned
                || $eventSchedule->videoRoom->alwaysReopen
            ) {
                $state = self::STATE_JOIN;
                $this->roomId = $eventSchedule->videoRoom->community->name;
                $this->roomPass = $eventSchedule->videoRoom->community->password;
            }
        } elseif ($isOwned && time() >= ($eventSchedule->dateTime - 360)) {
            $state = self::STATE_CREATE_VIDEO_ROOM;
        } elseif ($isOwned && time() < $eventSchedule->dateTime) {
            $state = self::STATE_CREATE_LATER;
        } elseif (!$isOwned) {
            $state = self::STATE_CHECK_LATER;
        }

        $this->state = $state ?? self::STATE_CHECK_LATER;

        $interests = $interests !== null ?
            $interests :
            $eventSchedule->interests->map(fn(EventScheduleInterest $i) => $i->interest)->toArray();

        $language = $eventSchedule->language;
        $this->interests = array_map(fn(Interest $i) => new InterestDTO($i), $interests);
        if ($language) {
            $this->interests = array_merge(
                [InterestDTO::createFromFields($language->id, $language->name, true)],
                $this->interests
            );
        }

        $this->festivalCode = $eventSchedule->festivalCode;
        if ($eventSchedule->festivalScene) {
            $this->festivalScene = new EventFestivalSceneResponse($eventSchedule->festivalScene);
        }

        $this->forMembersOnly = $eventSchedule->forMembersOnly;

        $this->language = $eventSchedule->language ? new LanguageDTO($eventSchedule->language) : null;
        if ($eventSchedule->club) {
            $this->club = new ClubSlimResponse($eventSchedule->club);
        }

        $this->participants = array_values($this->participants);

        $this->withToken = $eventSchedule->isTokensRequired;
    }

    public function getClub(): ?ClubSlimResponse
    {
        return $this->club;
    }
}
