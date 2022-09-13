<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\AmplitudeDataManager;
use Redis;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RequestAmplitudeSubscriber implements EventSubscriberInterface
{
    private TokenStorageInterface $tokenStorage;
    private AmplitudeDataManager $amplitudeDataManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AmplitudeDataManager $amplitudeDataManager
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->amplitudeDataManager = $amplitudeDataManager;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return;
        }

        $sessionId = $request->headers->get('amplSessionId');
        if ($sessionId !== null) {
            $this->amplitudeDataManager->saveSessionId($currentUser->id, $sessionId);
        }

        $deviceId = $request->headers->get('amplDeviceId');
        if ($deviceId !== null) {
            $this->amplitudeDataManager->saveDeviceId($currentUser->id, $deviceId);
        }

        $userAgent = $request->headers->get('user-agent');
        if ($userAgent !== null) {
            $version = $this->parseVersion($userAgent);
            if ($version !== null) {
                $this->amplitudeDataManager->saveAppVersion($currentUser->id, $version);
            }
        }
    }

    private function parseVersion(string $userAgent): ?array
    {
        if (preg_match('/\b(\w+)\s+([\w.]+)\/app\b/ui', $userAgent, $matches)) {
            return [$matches[1], $matches[2]];
        } else {
            return null;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 0]],
        ];
    }
}
