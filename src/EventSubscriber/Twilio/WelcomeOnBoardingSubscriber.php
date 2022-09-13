<?php

namespace App\EventSubscriber\Twilio;

use App\Entity\Activity\InviteWelcomeOnBoardingActivity;
use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomParticipantConnectedEvent;
use App\Repository\Activity\InviteWelcomeOnBoardingActivityRepository;
use App\Repository\Follow\FollowRepository;
use App\Service\ActivityManager;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WelcomeOnBoardingSubscriber implements EventSubscriberInterface
{
    private ActivityManager $activityManager;
    private InviteWelcomeOnBoardingActivityRepository $activityRepository;
    private NotificationManager $notificationManager;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ActivityManager $activityManager,
        InviteWelcomeOnBoardingActivityRepository $activityRepository,
        NotificationManager $notificationManager,
        EntityManagerInterface $entityManager
    ) {
        $this->activityManager = $activityManager;
        $this->activityRepository = $activityRepository;
        $this->notificationManager = $notificationManager;
        $this->entityManager = $entityManager;
    }

    public function onVideoRoomParticipantConnectedEvent(VideoRoomParticipantConnectedEvent $event)
    {
        if ($event->videoRoom->type === VideoRoom::TYPE_NATIVE) {
            return;
        }

        $participant = $event->user;
        if (!$participant) {
            return;
        }

        if (!$event->videoRoom->forPersonallyOnBoarding) {
            return;
        }

        $activity = $this->activityRepository->findOneBy([
            'videoRoom' => $event->videoRoom,
            'user' => $event->videoRoom->forPersonallyOnBoarding
        ]);

        if ($activity) {
            return;
        }

        $activity = new InviteWelcomeOnBoardingActivity(
            $event->videoRoom,
            $event->videoRoom->forPersonallyOnBoarding,
            $participant
        );
        $this->entityManager->persist($activity);

        $initiator = $event->videoRoom->forPersonallyOnBoarding;

        $this->notificationManager->sendNotifications(
            $event->videoRoom->forPersonallyOnBoarding,
            new ReactNativeVideoRoomNotification(
                $event->videoRoom,
                $this->activityManager->getActivityTitle($activity),
                $this->activityManager->getActivityDescription($activity),
                [

                    PushNotification::PARAMETER_INITIATOR_ID => $initiator->id,
                    PushNotification::PARAMETER_SPECIFIC_KEY => 'invite-welcome-on-boarding',
                    PushNotification::PARAMETER_IMAGE => $initiator->getAvatarSrc(300, 300),
                ],
                'join-the-room'
            )
        );

        $this->entityManager->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [VideoRoomParticipantConnectedEvent::class => 'onVideoRoomParticipantConnectedEvent'];
    }
}
