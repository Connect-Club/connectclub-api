<?php

namespace App\EventSubscriber;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\Exception\ApiException;
use App\Exception\ApiValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private BaseController $controller;
    private LoggerInterface $logger;

    public function __construct(BaseController $controller, LoggerInterface $logger)
    {
        $this->controller = $controller;
        $this->logger = $logger;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $this->handleHttpException($event);
        } elseif ($this->isProd()) {
            $this->logger->error($exception, ['exception' => $exception]);

            if ($exception instanceof ExceptionInterface) {
                $responseCode = Response::HTTP_NOT_FOUND;
                $errorCode = $exception->getMessage();
            } else {
                $responseCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                $errorCode = ErrorCode::V1_INTERNAL_SERVER_ERROR;
            }

            $event->setResponse(
                $this->controller->createErrorResponse(
                    $errorCode,
                    $responseCode
                )
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }

    private function handleHttpException(ExceptionEvent $event): void
    {
        /** @var HttpExceptionInterface $exception */
        $exception = $event->getThrowable();

        if ($exception instanceof BadRequestHttpException) {
            $message = $exception->getMessage() ?: ErrorCode::V1_BAD_REQUEST;
        } elseif ($exception instanceof ApiValidationException) {
            $message = $this->controller->formatConstraintViolationList($exception->getConstraintViolationList());
        } else {
            $message = $exception->getMessage();
        }

        $response = $this->controller->createErrorResponse(
            $message,
            $exception->getStatusCode(),
            $exception->getTrace()
        );
        $response->headers->add($exception->getHeaders());

        $event->setResponse($response);
    }

    private function isProd(): bool
    {
        return !isset($_ENV['APP_ENV']) || !in_array($_ENV['APP_ENV'], ['dev', 'codeception']);
    }
}
