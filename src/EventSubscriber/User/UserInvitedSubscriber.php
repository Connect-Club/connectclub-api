<?php

namespace App\EventSubscriber\User;

use App\Entity\Activity\IntroActivity;
use App\Entity\Activity\UserRegisteredActivity;
use App\Entity\User;
use App\Event\User\UserInvitedEvent;
use App\Repository\Activity\IntroActivityRepository;
use App\Repository\Activity\NewUserFromWaitingListActivityRepository;
use App\Repository\User\PhoneContactRepository;
use App\Service\MatchingClient;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserInvitedSubscriber implements EventSubscriberInterface
{
    private NewUserFromWaitingListActivityRepository $newUserFromWaitingListActivityRepository;
    private IntroActivityRepository $introActivityRepository;
    private MatchingClient $matchingClient;

    public function __construct(
        NewUserFromWaitingListActivityRepository $newUserFromWaitingListActivityRepository,
        IntroActivityRepository $introActivityRepository,
        MatchingClient $matchingClient
    ) {
        $this->newUserFromWaitingListActivityRepository = $newUserFromWaitingListActivityRepository;
        $this->introActivityRepository = $introActivityRepository;
        $this->matchingClient = $matchingClient;
    }

    public function onUserInvitedEvent(UserInvitedEvent $event)
    {
        $invitedUser = $event->getUser();

        if ($invitedUser->phone) {
            $this->newUserFromWaitingListActivityRepository->removeActivityWithPhoneNumber($invitedUser->phone);
        }

        $this->introActivityRepository->save(new IntroActivity($invitedUser));

        $this->matchingClient->publishEvent('userAdded', $invitedUser);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserInvitedEvent::class => 'onUserInvitedEvent',
        ];
    }
}
