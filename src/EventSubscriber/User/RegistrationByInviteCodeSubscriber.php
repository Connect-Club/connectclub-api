<?php

namespace App\EventSubscriber\User;

use App\Entity\Activity\NewUserRegisteredByInviteCodeActivity;
use App\Entity\User;
use App\Event\PostRegistrationUserEvent;
use App\Event\PreRegistrationUserEvent;
use App\Event\User\ChangeStateUserEvent;
use App\Repository\Club\ClubRepository;
use App\Repository\UserRepository;
use App\Service\ActivityManager;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RegistrationByInviteCodeSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private UserRepository $userRepository;
    private ActivityManager $activityManager;

    public function __construct(
        RequestStack $requestStack,
        UserRepository $userRepository,
        ActivityManager $activityManager
    ) {
        $this->requestStack = $requestStack;
        $this->userRepository = $userRepository;
        $this->activityManager = $activityManager;
    }

    public function onPostRegistrationUser(PostRegistrationUserEvent $postRegistrationUserEvent)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $inviteCode = $request->get('inviteCode');
        if (!Uuid::isValid($inviteCode)) {
            return;
        }

        $user = $postRegistrationUserEvent->user;
        $user->registerByInviteCode = $inviteCode;

        $this->userRepository->save($user);
    }

    public function onChangeState(ChangeStateUserEvent $changeStateUserEvent)
    {
        $user = $changeStateUserEvent->getUser();

        if ($user->registerByInviteCodeNotificationSend) {
            return;
        }

        if ($user->registerByInviteCode === null) {
            return;
        }

        $states = [User::STATE_WAITING_LIST, User::STATE_NOT_INVITED, User::STATE_INVITED, User::STATE_VERIFIED];
        if (!in_array($user->state, $states)) {
            return;
        }

        if (!$authorInvite = $this->userRepository->findOneBy(['inviteCode' => $user->registerByInviteCode])) {
            return;
        }

        $activity = new NewUserRegisteredByInviteCodeActivity($authorInvite, $user);
        $this->activityManager->fireActivityForUsers(
            $activity,
            [$authorInvite],
            new ReactNativePushNotification(
                $activity->getType(),
                $this->activityManager->getActivityTitle($activity),
                $this->activityManager->getActivityDescription($activity),
                [
                    PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                    PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(300, 300),
                    'inviteCode' => $user->registerByInviteCode,
                    'userId' => (string) $user->id,
                ]
            )
        );

        $user->registerByInviteCodeNotificationSend = true;
        $this->userRepository->save($user);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostRegistrationUserEvent::class => 'onPostRegistrationUser',
            ChangeStateUserEvent::class => 'onChangeState',
        ];
    }
}
