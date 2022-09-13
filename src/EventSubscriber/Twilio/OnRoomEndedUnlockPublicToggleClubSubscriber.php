<?php

namespace App\EventSubscriber\Twilio;

use App\Event\VideoRoomEndedEvent;
use App\Repository\Club\ClubRepository;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnRoomEndedUnlockPublicToggleClubSubscriber implements EventSubscriberInterface
{
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private ClubRepository $clubRepository;

    public function __construct(
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        ClubRepository $clubRepository
    ) {
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->clubRepository = $clubRepository;
    }

    public function onVideoRoomEndedEvent(VideoRoomEndedEvent $event)
    {
        if ($event->videoRoom->isPrivate ||
            !$event->videoRoom->eventSchedule ||
            !$event->videoRoom->eventSchedule->club ||
            $event->videoRoom->eventSchedule->club->togglePublicModeEnabled ||
            $event->videoRoom->eventSchedule->forMembersOnly) {
            return;
        }

        $participantsWithoutModerators = $this->videoMeetingParticipantRepository->findParticipantsExceptClubOwners(
            $event->videoRoom
        );

        if (!$participantsWithoutModerators) {
            return;
        }

        $event->videoRoom->eventSchedule->club->togglePublicModeEnabled = true;
        $this->clubRepository->save($event->videoRoom->eventSchedule->club);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VideoRoomEndedEvent::class => 'onVideoRoomEndedEvent',
        ];
    }
}
