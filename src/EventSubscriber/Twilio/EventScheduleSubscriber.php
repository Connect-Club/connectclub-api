<?php

namespace App\EventSubscriber\Twilio;

use App\Event\VideoRoomCreatedEvent;
use App\Service\EventManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventScheduleSubscriber implements EventSubscriberInterface
{
    private EventManager $eventManager;

    public function __construct(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }


    public function onVideoRoomCreatedEvent(VideoRoomCreatedEvent $event): void
    {
        $eventSchedule = $event->videoRoom->eventSchedule;

        if (!$eventSchedule) {
            return;
        }

        if ($event->videoRoom->isPrivate && !$eventSchedule->isPrivate) {
            return;
        }

        if ($event->videoMeeting && $event->videoMeeting->jitsiCounter > 1) {
            return;
        }

        $this->eventManager->sendNotifications($eventSchedule);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VideoRoomCreatedEvent::class => ['onVideoRoomCreatedEvent', -255],
        ];
    }
}
