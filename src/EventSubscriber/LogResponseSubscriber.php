<?php

namespace App\EventSubscriber;

use App\Service\EventLogManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Throwable;

class LogResponseSubscriber implements EventSubscriberInterface
{
    private EventLogManager $eventLogManager;
    private LoggerInterface $logger;

    public function __construct(EventLogManager $eventLogManager, LoggerInterface $logger)
    {
        $this->eventLogManager = $eventLogManager;
        $this->logger = $logger;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        return;

//        $request = var_export($event->getRequest(), true);
//        $response = $event->getResponse()->getContent();
//
//        try {
//            $this->eventLogManager->logEventCustomObject(
//                'request',
//                $event->getRequest()->getClientIp() ?? '',
//                $event->getRequest()->getBasePath(),
//                [
//                    'request' => $request,
//                    'response' => $response
//                ]
//            );
//        } catch (Throwable $e) {
//            $this->logger->error($e, ['exception' => $e]);
//        }
    }

    public static function getSubscribedEvents(): array
    {
        return ['kernel.response' => 'onKernelResponse'];
    }
}
