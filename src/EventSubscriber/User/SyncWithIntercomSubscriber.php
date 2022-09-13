<?php

namespace App\EventSubscriber\User;

use App\Entity\User;
use App\Event\User\ChangeStateUserEvent;
use App\Message\SyncWithIntercomMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SyncWithIntercomSubscriber implements EventSubscriberInterface
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function onChangeState(ChangeStateUserEvent $changeStateUserEvent)
    {
        $user = $changeStateUserEvent->getUser();

        if ($user->state !== User::STATE_VERIFIED) {
            return;
        }

        $this->bus->dispatch(new SyncWithIntercomMessage($user));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ChangeStateUserEvent::class => 'onChangeState',
        ];
    }
}
