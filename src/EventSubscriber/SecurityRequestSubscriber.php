<?php

namespace App\EventSubscriber;

use App\Annotation\Lock;
use App\Annotation\Security as SecurityAnnotation;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\Entity\User;
use Closure;
use Doctrine\Common\Annotations\Reader;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionFunction;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;

class SecurityRequestSubscriber implements EventSubscriberInterface
{
    private Reader $reader;
    private Security $security;
    private BaseController $baseController;
    private LoggerInterface $logger;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        Reader $reader,
        Security $security,
        BaseController $controller,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ) {
        $this->baseController = $controller;
        $this->reader = $reader;
        $this->security = $security;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }

    public function onControllerEvent(ControllerArgumentsEvent $event)
    {
        try {
            $controller = $event->getController();
            $reflectionFunction = new ReflectionFunction(Closure::fromCallable($controller));
            $reflectionMethod = $reflectionFunction->getClosureScopeClass()->getMethod($reflectionFunction->name);
        } catch (ReflectionException $e) {
            $this->logger->debug(sprintf('LockRequestSubscriber reflection error: %s', $e->getMessage()));
            return;
        }

        /** @var SecurityAnnotation|null $security */
        $security = $this->reader->getMethodAnnotation($reflectionMethod, SecurityAnnotation::class);
        if (!$security) {
            return;
        }

        if ($security->role && !$this->security->isGranted($security->role)) {
            $event->setController(new class($this->baseController) {
                private BaseController $controller;

                public function __construct(BaseController $controller)
                {
                    $this->controller = $controller;
                }


                public function __invoke(): Response
                {
                    return $this->controller->createErrorResponse(
                        [ErrorCode::V1_ACCESS_DENIED],
                        Response::HTTP_FORBIDDEN
                    );
                }
            });
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.controller_arguments' => ['onControllerEvent', 2049],
        ];
    }
}
