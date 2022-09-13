<?php

namespace App\EventSubscriber\Twilio;

use App\Annotation\Lock;
use App\Event\VideoRoomEvent;
use App\Event\VideoRoomParticipantDisconnectedEvent;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\EventLogManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;

class OnRoomParticipantDisconnectedSubscriber implements EventSubscriberInterface
{
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private VideoMeetingRepository $videoMeetingRepository;
    private UserRepository $userRepository;
    private EventLogManager $eventLogManager;
    private VideoRoomRepository $videoRoomRepository;
    private LockFactory $lockFactory;
    private LoggerInterface $logger;

    public function __construct(
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        VideoMeetingRepository $videoMeetingRepository,
        UserRepository $userRepository,
        EventLogManager $eventLogManager,
        VideoRoomRepository $videoRoomRepository,
        LockFactory $lockFactory,
        LoggerInterface $logger
    ) {
        $this->videoMeetingRepository = $videoMeetingRepository;
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->userRepository = $userRepository;
        $this->eventLogManager = $eventLogManager;
        $this->logger = $logger;
        $this->lockFactory = $lockFactory;
        $this->videoRoomRepository = $videoRoomRepository;
    }

    public function onVideoRoomParticipantDisconnectedEvent(VideoRoomParticipantDisconnectedEvent $event)
    {
        if (!$videoRoomActiveMeeting = $event->videoMeeting) {
            $this->logger->error('Meeting not found', $event->getContext());
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

                foreach ($room->guests as $k => [$actualGuestId,]) {
                    if ($actualGuestId === $guestId) {
                        $room->guests[$k][2] = time();
                        break;
                    }
                }

                $this->videoRoomRepository->save($room);
                $lock->release();
            } else {
                $this->logger->error('Participant sid not found for room', $event->getContext());
            }

            return;
        }

        if ($videoRoomActiveMeeting->initiator == VideoRoomEvent::INITIATOR_JITSI) {
            $videoRoomActiveMeetingParticipant = $this->videoMeetingParticipantRepository->findOneBy([
                'jitsiEndpointUuid' => $event->jitsiEndpointUuid,
            ]);
        } else {
            $videoRoomActiveMeetingParticipant = $this->videoMeetingParticipantRepository->findOneBy([
                'videoMeeting' => $videoRoomActiveMeeting,
                'participant' => $user,
            ]);
        }

        if (!$videoRoomActiveMeetingParticipant) {
            $this->logger->error('Participant not found for fill endTime', $event->getContext());

            return;
        }

        $this->eventLogManager->logEvent(
            $videoRoomActiveMeetingParticipant,
            'endpoint_expired_set_end_time',
            $event->getContext()
        );

        $videoRoomActiveMeetingParticipant->endTime = $event->unixTimestamp;
        $this->videoMeetingParticipantRepository->save($videoRoomActiveMeetingParticipant);

        $videoRoomActiveMeetingParticipant->participant->onlineInVideoRoom = false;
        $this->userRepository->save($videoRoomActiveMeetingParticipant->participant);
    }

    public static function getSubscribedEvents()
    {
        return [
            VideoRoomParticipantDisconnectedEvent::class => 'onVideoRoomParticipantDisconnectedEvent',
        ];
    }
}
