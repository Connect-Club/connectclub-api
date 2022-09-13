<?php

namespace App\EventSubscriber\Twilio;

use App\DTO\V1\VideoRoom\VideoRoomResponse;
use App\Entity\Activity\StartedVideoRoomActivity;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomCreatedEvent;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\EventLogManager;
use App\Service\VideoRoomNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class OnRoomCreateSubscriber implements EventSubscriberInterface
{
    private VideoRoomRepository $videoRoomRepository;
    private VideoMeetingRepository $videoMeetingRepository;
    private EventLogManager $eventLogManager;
    private LockFactory $lockFactory;
    private EntityManagerInterface $entityManager;
    private VideoRoomNotifier $roomNotifier;
    private SerializerInterface $serializer;

    public function __construct(
        VideoRoomRepository $videoRoomRepository,
        EventLogManager $eventLogManager,
        VideoMeetingRepository $videoMeetingRepository,
        LockFactory $lockFactory,
        EntityManagerInterface $entityManager,
        VideoRoomNotifier $roomNotifier,
        SerializerInterface $serializer
    ) {
        $this->videoRoomRepository = $videoRoomRepository;
        $this->eventLogManager = $eventLogManager;
        $this->videoMeetingRepository = $videoMeetingRepository;
        $this->lockFactory = $lockFactory;
        $this->entityManager = $entityManager;
        $this->roomNotifier = $roomNotifier;
        $this->serializer = $serializer;
    }

    public function onVideoRoomCreatedEvent(VideoRoomCreatedEvent $event)
    {
        $videoRoom = $event->videoRoom;

        if ($videoRoom->type === VideoRoom::TYPE_NEW && !$videoRoom->startedAt) {
            $videoRoom->startedAt = time();
            $this->videoRoomRepository->save($videoRoom);
        }

        $isEmptyMeeting = !$event->videoMeeting || $event->videoMeeting->isEmptyMeeting;
        if ($isEmptyMeeting && $videoRoom->type === VideoRoom::TYPE_NEW) {
            if (!$videoRoom->isPrivate ||
                ($videoRoom->eventSchedule && $videoRoom->eventSchedule->isPrivate)) {
                $this->roomNotifier->notifyStarted($videoRoom);
            }
        }

        $videoRoomActiveMeeting = new VideoMeeting(
            $videoRoom,
            $event->parameters->roomSid,
            $event->unixTimestamp,
            $event->initiator
        );

        try {
            $videoRoomSnapshot = $this->serializer->serialize(new VideoRoomResponse($videoRoom), 'json');
            $videoRoomActiveMeeting->videoRoomSnapshotData = json_decode($videoRoomSnapshot, true);
        } catch (Throwable $exception) {
        }

        if ($activeMeeting = $event->videoMeeting) {
            $lock = $this->lockFactory->createLock(
                'jistiCounterForVideoRoom_'.$videoRoom->community->name,
                10,
                true
            );
            $lock->acquire(true);

            $this->entityManager->refresh($activeMeeting);

            $activeMeeting->jitsiCounter += 1;
            $videoRoomActiveMeeting = $activeMeeting;
            $eventCode = 'conference_create_chose_exists_meeting';

            $lock->release();
        } else {
            $videoRoomActiveMeeting->jitsiCounter = 1;
            $eventCode = 'conference_create_create_new_meeting';
            $event->videoMeeting = $videoRoomActiveMeeting;
        }

        if ($videoRoomActiveMeeting->isEmptyMeeting) {
            $videoRoomActiveMeeting->isEmptyMeeting = false;
            $videoRoomActiveMeeting->startTime = time();
        }

        if ($videoRoom->doneAt !== null) {
            $this->eventLogManager->logEvent($videoRoom, 'reopen_room', ['lastValue' => $videoRoom->doneAt]);
            $videoRoom->doneAt = null;
            $this->videoRoomRepository->save($videoRoom);
        }

        $this->videoMeetingRepository->save($videoRoomActiveMeeting);

        $this->eventLogManager->logEvent($videoRoomActiveMeeting, $eventCode, $event->getContext());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VideoRoomCreatedEvent::class => ['onVideoRoomCreatedEvent', 255],
        ];
    }
}
