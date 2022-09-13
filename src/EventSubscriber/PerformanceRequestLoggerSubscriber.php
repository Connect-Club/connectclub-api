<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class PerformanceRequestLoggerSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    /**
     * PerformanceRequestLoggerSubscriber constructor.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onRequestEvent(RequestEvent $event)
    {
        $event->getRequest()->attributes->set('start_micro_time', microtime(true));
    }

    public function onResponseEvent(ResponseEvent $event)
    {
        $request = $event->getRequest();

        $microTimeStart = $request->attributes->get('start_micro_time');
        if (!$microTimeStart) {
            return;
        }

        $result = microtime(true) - $microTimeStart;

        $method = $request->getMethod();
        $requestUri = $request->getRequestUri();

        if ($result > 2) {
            $this->logger->warning(sprintf('Performance %s %s long execution time: %s', $method, $requestUri, $result));
        }
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            RequestEvent::class => 'onRequestEvent',
            ResponseEvent::class => 'onResponseEvent',
        ];
    }
}
