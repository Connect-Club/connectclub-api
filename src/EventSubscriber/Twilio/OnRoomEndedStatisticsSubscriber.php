<?php

namespace App\EventSubscriber\Twilio;

use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomEndedEvent;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Service\Amplitude\AmplitudeManager;
use App\Service\Amplitude\AmplitudeUser;
use App\Service\EventLogManager;
use App\Service\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OnRoomEndedStatisticsSubscriber implements EventSubscriberInterface
{
    private MessageBusInterface $bus;
    private EventLogManager $eventLogManager;
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private AmplitudeManager $amplitudeManager;

    public function __construct(
        MessageBusInterface $bus,
        EventLogManager $eventLogManager,
        AmplitudeManager $amplitudeManager,
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository
    ) {
        $this->bus = $bus;
        $this->eventLogManager = $eventLogManager;
        $this->amplitudeManager = $amplitudeManager;
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
    }

    public function onVideoRoomEndedEvent(VideoRoomEndedEvent $event)
    {
        if (!$event->videoMeeting) {
            return;
        }

        $nonClosedMeetings = $event->videoRoom->meetings->matching(
            Criteria::create()->where(
                Criteria::expr()->isNull('endTime')
            )
        );

        if ($nonClosedMeetings->count() > 0) {
            $nonClosedMeetings->map(
                fn(VideoMeeting $m) => $this->eventLogManager->logEvent($m, 'not_closed_meeting_close_stats')
            );

            return;
        }

        $videoRoom = $event->videoRoom;
        $participants = $this->videoMeetingParticipantRepository->findParticipantsWithTimeInterval($videoRoom, 0);

        $allTesters = true;
        foreach ($participants as $participant) {
            if (!$participant['is_tester']) {
                $allTesters = false;
                break;
            }
        }

        $meetingStartTimes = $meetingEndTimes = [];
        foreach ($videoRoom->meetings as $meeting) {
            $meetingStartTimes[] = $meeting->startTime;
            $meetingEndTimes[] = $meeting->endTime;
        }
        $totalTime = max($meetingEndTimes) - min($meetingStartTimes);

        $owner = $videoRoom->community->owner;
        if ($totalTime >= 300 && !$allTesters) {
            $this->eventLogManager->logEvent($videoRoom, 'send_amplitude_statistics_five_minutes');
            $this->bus->dispatch(
                new AmplitudeEventStatisticsMessage('api.great_or_equal.five_minutes', [], $owner)
            );
        }

        foreach ($participants as $participant) {
            if ($participant['time_on_room'] >= 120 && !$participant['is_tester']) {
                $this->amplitudeManager->addEventToBatch(
                    AmplitudeUser::createFromUserId($participant['user_id']),
                    'api.participant.connect_more_two_minutes'
                );
            }
        }
        $this->amplitudeManager->flushBatch();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VideoRoomEndedEvent::class => ['onVideoRoomEndedEvent', -255],
        ];
    }
}
