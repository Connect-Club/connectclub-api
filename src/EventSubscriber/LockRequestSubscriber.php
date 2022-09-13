<?php

namespace App\EventSubscriber;

use App\Annotation\Lock;
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

class LockRequestSubscriber implements EventSubscriberInterface
{
    const LOCK_REQUEST_ATTRIBUTE_KEY = 'lock';

    private Reader $reader;
    private LockFactory $lock;
    private BaseController $baseController;
    private LoggerInterface $logger;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        Reader $reader,
        LockFactory $lock,
        BaseController $controller,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ) {
        $this->baseController = $controller;
        $this->reader = $reader;
        $this->lock = $lock;
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

        /** @var Lock|null $lock */
        $lock = $this->reader->getMethodAnnotation($reflectionMethod, Lock::class);
        if (!$lock) {
            return;
        }

        if ($lock->personal) {
            if (!$this->tokenStorage->getToken() || !$this->tokenStorage->getToken()->getUser()) {
                return;
            }

            /** @var User $user */
            $user = $this->tokenStorage->getToken()->getUser();

            $key = $lock->code . '_user_' . $user->getId();
        } else {
            $key = $lock->code;
        }

        $lock = $this->lock->createLock($key, $lock->timeout, false);
        if (!$lock->acquire()) {
            $event->setController(new class($this->baseController) {
                private BaseController $controller;

                public function __construct(BaseController $controller)
                {
                    $this->controller = $controller;
                }


                public function __invoke(): Response
                {
                    return $this->controller->createErrorResponse(
                        [ErrorCode::V1_ERROR_ACTION_LOCK],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            });

            return;
        }

        $event->getRequest()->attributes->set(self::LOCK_REQUEST_ATTRIBUTE_KEY, $lock);
    }

    public function onResponseEvent(ResponseEvent $event)
    {
        $requestAttributes = $event->getRequest()->attributes;

        if (!$requestAttributes->has(self::LOCK_REQUEST_ATTRIBUTE_KEY)) {
            return;
        }
        /** @var LockInterface $lock */
        $lock = $requestAttributes->get(self::LOCK_REQUEST_ATTRIBUTE_KEY);

        if (!$lock instanceof LockInterface) {
            throw new RuntimeException(sprintf(
                'Request lock attribute expected %s got %s',
                LockInterface::class,
                get_class($lock)
            ));
        }

        $lock->release();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.response'             => ['onResponseEvent', 2048],
            'kernel.controller_arguments' => ['onControllerEvent', 2048],
        ];
    }
}
