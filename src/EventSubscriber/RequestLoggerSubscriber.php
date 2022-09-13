<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class RequestLoggerSubscriber implements EventSubscriberInterface
{
    /** @var RequestMatcherInterface|UrlMatcherInterface */
    private $matcher;
    private LoggerInterface $logger;

    /**
     * @param UrlMatcherInterface|RequestMatcherInterface $matcher
     * @param LoggerInterface $logger
     */
    public function __construct($matcher, LoggerInterface $logger)
    {
        $this->matcher = $matcher;
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        try {
            // matching a request is more powerful than matching a URL path + context, so try that first
            if ($this->matcher instanceof RequestMatcherInterface) {
                $parameters = $this->matcher->matchRequest($request);
            } else {
                $parameters = $this->matcher->match($request->getPathInfo());
            }

            $this->logger->info('Matched route "{route}".', [
                'route' => $parameters['_route'] ?? 'n/a',
                'route_parameters' => $parameters,
                'request_uri' => $request->getUri(),
                'method' => $request->getMethod(),
            ]);
        } catch (ResourceNotFoundException | NoConfigurationException | MethodNotAllowedException $e) {
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 0]],
        ];
    }
}
