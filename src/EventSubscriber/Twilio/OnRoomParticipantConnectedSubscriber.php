<?php

namespace App\EventSubscriber\Twilio;

use App\Entity\Event\EventScheduleInterest;
use App\Entity\Interest\Interest;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoomHistory;
use App\Event\VideoRoomEvent;
use App\Event\VideoRoomParticipantConnectedEvent;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Repository\VideoChat\VideoRoomHistoryRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\EventLogManager;
use App\Service\MatchingClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class OnRoomParticipantConnectedSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private EventLogManager $eventLogManager;
    private UserRepository $userRepository;
    private MatchingClient $matchingClient;
    private MessageBusInterface $bus;
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private VideoRoomHistoryRepository $videoRoomHistoryRepository;
    private VideoRoomRepository $videoRoomRepository;
    private LockFactory $lockFactory;

    public function __construct(
        LoggerInterface $logger,
        EventLogManager $eventLogManager,
        UserRepository $userRepository,
        MatchingClient $matchingClient,
        MessageBusInterface $bus,
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        VideoRoomHistoryRepository $videoRoomHistoryRepository,
        VideoRoomRepository $videoRoomRepository,
        LockFactory $lockFactory
    ) {
        $this->logger = $logger;
        $this->eventLogManager = $eventLogManager;
        $this->userRepository = $userRepository;
        $this->matchingClient = $matchingClient;
        $this->bus = $bus;
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->videoRoomHistoryRepository = $videoRoomHistoryRepository;
        $this->videoRoomRepository = $videoRoomRepository;
        $this->lockFactory = $lockFactory;
    }

    public function onVideoRoomParticipantConnectedEvent(VideoRoomParticipantConnectedEvent $event)
    {
        $videoRoomActiveMeeting = $event->videoMeeting;
        if (!$videoRoomActiveMeeting) {
            $this->logger->error('Meeting with sid not found in event', $event->getContext());
            return;
        }

        if (!$user = $event->user) {
            if (mb_strpos($event->parameters->participantIdentity, 'guest-') !== false) {
                $room = $event->videoRoom;
                $lock = $this->lockFactory->createLock('working_video_room_guests_'.$room->id);

                $wasNotBlocking = $lock->acquire(true);
                if (!$wasNotBlocking) {
                    $room = $this->videoRoomRepository->refresh($room);
                }

                $guestId = str_replace('guest-', '', $event->parameters->participantIdentity);
                if (!$room->guests) {
                    $room->guests = [];
                }

                $room->guests[] = [$guestId, time(), null];
                $this->videoRoomRepository->save($room);

                $lock->release();
            } else {
                $this->logger->error('Participant sid not found for room', $event->getContext());
            }

            return;
        }

        if ($event->initiator == VideoRoomEvent::INITIATOR_JITSI) {
            $meetingParticipant = $this->videoMeetingParticipantRepository->findOneBy([
                'jitsiEndpointUuid' => $event->jitsiEndpointUuid,
                'endTime' => null,
            ]);

            if ($meetingParticipant) {
                $this->eventLogManager->logEvent($meetingParticipant, 'endpoint_created.participant_already_exists');
                $event->stopPropagation();
                return;
            }

            $participantsNeedClose = $this->videoMeetingParticipantRepository->findBy([
                'participant' => $user,
                'endTime' => null,
            ]);

            foreach ($participantsNeedClose as $participantNeedClose) {
                $participantNeedClose->endTime = time();
                $participantNeedClose->participant->onlineInVideoRoom = false;
                $this->userRepository->save($participantNeedClose->participant);
                $this->videoMeetingParticipantRepository->save($participantNeedClose);
                $this->eventLogManager->logEvent(
                    $participantNeedClose,
                    'endpoint_created_auto_close_previous_participant',
                    $event->getContext()
                );
            }
        }

        $videoRoomHistory = $this->videoRoomHistoryRepository->findOneBy([
            'videoRoom' => $videoRoomActiveMeeting->videoRoom,
            'user' => $user,
        ]);

        if ($videoRoomHistory) {
            $videoRoomHistory->joinedAt = $event->unixTimestamp;
            $videoRoomHistory->password = $videoRoomActiveMeeting->videoRoom->community->password;
        } else {
            $videoRoomHistory = new VideoRoomHistory(
                $videoRoomActiveMeeting->videoRoom,
                $user,
                $event->unixTimestamp
            );
        }

        if (!$user->isTester) {
            $participantAlreadyExists = $this->videoMeetingParticipantRepository->findUserParticipantInVideoRoom(
                $videoRoomActiveMeeting->videoRoom,
                $user
            );

            if (!$participantAlreadyExists) {
                try {
                    $room = $videoRoomActiveMeeting->videoRoom;
                    $eventSchedule = $room->eventSchedule;

                    $this->bus->dispatch(new AmplitudeEventStatisticsMessage(
                        'api.participant.room_connected',
                        [
                            'eventScheduleId' => $eventSchedule ? $eventSchedule->id->toString() : null,
                            'clubSlug' => $eventSchedule && $eventSchedule->club ? $eventSchedule->club->slug : null,
                        ],
                        $user
                    ));
                } catch (Throwable $exception) {
                    $this->logger->error($exception, ['exception' => $exception]);
                }
            }
        }

        $videoMeetingParticipant = new VideoMeetingParticipant(
            $videoRoomActiveMeeting,
            $user,
            $event->unixTimestamp,
            $event->jitsiEndpointUuid,
        );
        $videoMeetingParticipant->jitsiConferenceId = $event->parameters->roomSid;
        $videoMeetingParticipant->endpointAllowIncomingMedia = $event->endpointAllowIncomingMedia;

        $user->onlineInVideoRoom = true;
        $user->lastTimeActivity = time();

        $this->videoRoomHistoryRepository->save($videoRoomHistory);
        $this->videoMeetingParticipantRepository->save($videoMeetingParticipant);
        $this->userRepository->save($user);

        $interests = $videoRoomActiveMeeting->videoRoom->eventSchedule ?
            $videoRoomActiveMeeting->videoRoom
                                   ->eventSchedule
                                   ->interests->map(fn(EventScheduleInterest $i) => $i->interest)
                                   ->toArray() :
            $videoRoomActiveMeeting->videoRoom->community->owner->interests->toArray();

        if ($event->endpointAllowIncomingMedia) {
            $this->matchingClient->publishEventOwnedBy('userMeetingStageJoin', $user, [
                'id' => $videoRoomActiveMeeting->videoRoom->community->name,
                'interest_id' => array_map(fn(Interest $i) => $i->id, $interests),
            ]);
        } else {
            $this->matchingClient->publishEventOwnedBy('userMeetingParticipate', $user, [
                'id' => $videoRoomActiveMeeting->videoRoom->community->name,
                'interest_id' => array_map(fn(Interest $i) => $i->id, $interests),
            ]);
        }

        $this->eventLogManager->logEvent(
            $videoMeetingParticipant,
            'endpoint_created_create_participant',
            $event->getContext()
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [VideoRoomParticipantConnectedEvent::class => ['onVideoRoomParticipantConnectedEvent', -255]];
    }
}
