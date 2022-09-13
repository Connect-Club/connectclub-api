<?php

namespace App\EventSubscriber;

use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class RequestIdSubscriber implements EventSubscriberInterface
{
    public function onRequestEvent(RequestEvent $event)
    {
        if (!$event->getRequest()->attributes->has('request_id')) {
            $event->getRequest()->attributes->set('request_id', Uuid::uuid4()->toString());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => ['onRequestEvent', 2048],
        ];
    }
}
