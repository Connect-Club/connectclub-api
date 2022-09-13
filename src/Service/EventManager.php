<?php

namespace App\Service;

use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\User;
use App\Repository\Event\EventScheduleSubscriptionRepository;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;

class EventManager
{
    private NotificationManager $notificationManager;
    private EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository;

    public function __construct(
        EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository,
        NotificationManager $notificationManager
    ) {
        $this->eventScheduleSubscriptionRepository = $eventScheduleSubscriptionRepository;
        $this->notificationManager = $notificationManager;
    }

    public function sendNotifications(EventSchedule $eventSchedule)
    {
        if (!$eventSchedule->videoRoom) {
            return;
        }

        $subscriptions = $this->eventScheduleSubscriptionRepository->findSubscriptionOnEvent($eventSchedule, true);

        $subscribedUsers = array_map(fn(EventScheduleSubscription $s) => $s->user, $subscriptions);
        $subscribedIds = array_map(fn(EventScheduleSubscription $s) => $s->id->toString(), $subscriptions);

        $this->notificationManager->setMode(NotificationManager::MODE_BATCH);
        array_map(
            fn(User $user) => $this->notificationManager->sendNotifications(
                $user,
                new ReactNativeVideoRoomNotification(
                    $eventSchedule->videoRoom,
                    'notifications.event_schedule_subscription_title',
                    'notifications.event_schedule_subscription',
                    [

                        PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                        PushNotification::PARAMETER_SPECIFIC_KEY => 'event-schedule-subscription',
                    ],
                    'video-room',
                    [
                        '%meetingName%' => $eventSchedule->videoRoom->community->description,
                    ]
                )
            ),
            $subscribedUsers
        );

        $this->notificationManager->flushBatch();
        $this->notificationManager->setMode(NotificationManager::MODE_SERIAL);

        $this->eventScheduleSubscriptionRepository->markSubscriptionsAsHandled($subscribedIds);
    }
}
