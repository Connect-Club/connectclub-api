<?php

namespace App\EventSubscriber;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class OnRequestBannedUserSubscriber implements EventSubscriberInterface
{
    private TokenStorageInterface $tokenStorage;
    private BaseController $baseController;

    public function __construct(TokenStorageInterface $tokenStorage, BaseController $baseController)
    {
        $this->tokenStorage = $tokenStorage;
        $this->baseController = $baseController;
    }

    public function onControllerEvent(ControllerArgumentsEvent $event)
    {
        if ($this->tokenStorage->getToken() && $user = $this->tokenStorage->getToken()->getUser()) {
            if (!$user instanceof User || null === $user->bannedAt) {
                return;
            }

            $event->setController(new class ($this->baseController, $user->banComment) {
                private BaseController $controller;
                private ?string $reason = null;

                public function __construct(BaseController $controller, ?string $reason = null)
                {
                    $this->controller = $controller;
                    $this->reason = $reason;
                }

                public function __invoke(): Response
                {
                    return $this->controller->createErrorResponse(
                        $this->reason ?? ErrorCode::V1_USER_BANNED,
                        Response::HTTP_FORBIDDEN
                    );
                }
            });
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.controller_arguments' => ['onControllerEvent', 3072],
        ];
    }
}
