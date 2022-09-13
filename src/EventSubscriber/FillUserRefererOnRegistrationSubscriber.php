<?php

namespace App\EventSubscriber;

use App\Event\PreRegistrationUserEvent;
use App\Repository\Club\ClubRepository;
use App\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FillUserRefererOnRegistrationSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private UserRepository $userRepository;
    private ClubRepository $clubRepository;

    public function __construct(
        RequestStack $requestStack,
        UserRepository $userRepository,
        ClubRepository $clubRepository
    ) {
        $this->requestStack = $requestStack;
        $this->userRepository = $userRepository;
        $this->clubRepository = $clubRepository;
    }

    public function onPreRegistrationUser(PreRegistrationUserEvent $preRegistrationUserEvent)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $refererId = (int) $request->get('referer_id');
        if ($refererId) {
            $preRegistrationUserEvent->user->referer = $this->userRepository->find($refererId);
        }

        $source = $request->get('source');
        if ($source !== null) {
            $preRegistrationUserEvent->user->source = $source;
        }

        $preRegistrationUserEvent->user->utmCompaign = $request->get('utm_campaign');
        $preRegistrationUserEvent->user->utmSource = $request->get('utm_source');
        $preRegistrationUserEvent->user->utmContent = $request->get('utm_content');

        $clubId = $request->get('clubId');
        if ($clubId !== null && Uuid::isValid($clubId)) {
            $preRegistrationUserEvent->user->registeredByClubLink = $this->clubRepository->find($clubId);
            $preRegistrationUserEvent->user->onBoardingNotificationAlreadySend = true;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreRegistrationUserEvent::class => 'onPreRegistrationUser',
        ];
    }
}
