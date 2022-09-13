<?php

namespace App\EventSubscriber\User;

use App\Entity\Activity\JoinDiscordActivity;
use App\Entity\User;
use App\Event\User\ChangeStateUserEvent;
use App\Repository\Activity\JoinDiscordActivityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserVerifiedSubscriber implements EventSubscriberInterface
{
    private JoinDiscordActivityRepository $joinDiscordActivityRepository;

    public function __construct(
        JoinDiscordActivityRepository $joinDiscordActivityRepository
    ) {
        $this->joinDiscordActivityRepository = $joinDiscordActivityRepository;
    }

    public function onChangeState(ChangeStateUserEvent $changeStateUserEvent)
    {
        $user = $changeStateUserEvent->getUser();

        if (User::STATE_VERIFIED !== $user->state) {
            return;
        }

        $activity = new JoinDiscordActivity($user);
        $this->joinDiscordActivityRepository->save($activity);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ChangeStateUserEvent::class => 'onChangeState',
        ];
    }
}
