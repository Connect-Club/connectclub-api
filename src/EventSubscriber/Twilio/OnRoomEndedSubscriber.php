<?php

namespace App\EventSubscriber\Twilio;

use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Event\SlackNotificationEvent;
use App\Event\VideoRoomEndedEvent;
use App\Event\VideoRoomEvent;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\EventLogManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;

class OnRoomEndedSubscriber implements EventSubscriberInterface
{
    private VideoMeetingRepository $videoMeetingRepository;
    private EventDispatcherInterface $eventDispatcher;
    private EventLogManager $eventLogManager;
    private VideoRoomRepository $videoRoomRepository;
    private LockFactory $lockFactory;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        VideoRoomRepository $videoRoomRepository,
        VideoMeetingRepository $videoMeetingRepository,
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface $entityManager,
        EventLogManager $eventLogManager,
        LockFactory $lockFactory,
        LoggerInterface $logger
    ) {
        $this->videoRoomRepository = $videoRoomRepository;
        $this->videoMeetingRepository = $videoMeetingRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventLogManager = $eventLogManager;
        $this->lockFactory = $lockFactory;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function onVideoRoomEndedEvent(VideoRoomEndedEvent $event)
    {
        $videoRoomActiveMeeting = $event->videoMeeting;
        if (!$videoRoomActiveMeeting) {
            $this->logger->error(sprintf(
                'Meeting with sid not found in event = %s',
                self::class
            ), ['meeting_sid' => $event->parameters->roomSid]);

            return;
        }

        if ($event->initiator == VideoRoomEvent::INITIATOR_JITSI) {
            $lock = $this->lockFactory->createLock(
                'jistiCounterForVideoRoom_'.$videoRoomActiveMeeting->videoRoom->community->name,
                10,
                true
            );
            $lock->acquire(true);

            $this->entityManager->refresh($videoRoomActiveMeeting);

            $videoRoomActiveMeeting->jitsiCounter -= 1;
            if ($videoRoomActiveMeeting->jitsiCounter > 0) {
                $this->eventLogManager->logEvent(
                    $videoRoomActiveMeeting,
                    'conference_expired_jitsi_counter_decrement',
                    $event->getContext()
                );
                $this->videoMeetingRepository->save($videoRoomActiveMeeting);
                $lock->release();
                return;
            }
            $lock->release();
        }

        $videoRoomActiveMeeting->endTime = $event->unixTimestamp;
        $onlineParticipants = $videoRoomActiveMeeting
                                    ->participants
                                    ->filter(fn(VideoMeetingParticipant $p) => !$p->endTime);

        foreach ($onlineParticipants as $onlineParticipant) {
            $this->eventLogManager->logEvent(
                $onlineParticipant,
                'conference_expired_fill_end_time_online_participant',
                $event->getContext()
            );
            $onlineParticipant->endTime = $event->unixTimestamp;
            $this->videoMeetingRepository->save($onlineParticipant);

            $onlineParticipant->participant->onlineInVideoRoom = false;
            $this->videoMeetingRepository->save($onlineParticipant->participant);
        }

        $videoRoom = $event->videoRoom;
        if ($videoRoom->type == VideoRoom::TYPE_NEW) {
            $videoRoom->doneAt = time();
            $this->videoRoomRepository->save($videoRoom);
        }

        $this->videoMeetingRepository->save($videoRoomActiveMeeting);

        $this->eventLogManager->logEvent($videoRoomActiveMeeting, 'conference_expired', $event->getContext());

        $this->eventDispatcher->dispatch(new SlackNotificationEvent($videoRoomActiveMeeting));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VideoRoomEndedEvent::class => 'onVideoRoomEndedEvent',
        ];
    }
}
