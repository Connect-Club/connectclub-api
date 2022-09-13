<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\Entity\Notification\Notification;
use App\Entity\Notification\NotificationStatistic;
use App\Repository\Notification\NotificationRepository;
use App\Repository\Notification\NotificationStatisticRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\ReactNativePushNotification;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class NotificationController.
 *
 * @Route("/notification")
 */
class NotificationController extends BaseController
{
    private NotificationStatisticRepository $notificationStatisticRepository;
    private NotificationRepository $notificationRepository;

    public function __construct(
        NotificationStatisticRepository $notificationStatisticRepository,
        NotificationRepository $notificationRepository
    ) {
        $this->notificationStatisticRepository = $notificationStatisticRepository;
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * @Route("/statistic/{code}", methods={"POST"})
     * @SWG\Post(
     *     description="Notification click statistics",
     *     summary="Notification click statistics",
     *     tags={"Notification"},
     *     @SWG\Response(response="200", description="Success")
     * )
     */
    public function statistic(string $code, LoggerInterface $logger): JsonResponse
    {
        if (Uuid::isValid($code)) {
            $notification = $this->notificationRepository->find($code);

            if (!$notification) {
                $logger->error('Notification not found', ['id' => $code]);
            }

            $notification->markAsOpened();
            $this->notificationRepository->save($notification);
        }

        $this->notificationStatisticRepository->save(new NotificationStatistic($this->getUser(), $code));

        return $this->handleResponse([]);
    }

    /**
     * @Route("/statistic/{state}/{id}", methods={"POST"})
     * @SWG\Post(
     *     description="Notification sent statistics",
     *     summary="Notification sent statistics",
     *     tags={"Notification"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="400", description="Bad id"),
     *     @SWG\Response(response="404", description="Notification not found"),
     * )
     */
    public function sent(string $id, string $state): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $notification = $this->notificationRepository->find($id);
        if (!$notification) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        switch ($state) {
            case Notification::STATUS_SEND:
            case Notification::STATUS_ERROR:
                $notification->markAsSend();
                $notification->status = $state;
                break;
        }

        $this->notificationRepository->save($notification);

        return $this->handleResponse([]);
    }

    /**
     * @Route("/test", methods={"POST"})
     */
    public function testNotifications(
        Request $request,
        NotificationManager $notificationManager,
        UserRepository $userRepository
    ) {
        if ($_ENV['STAGE'] != 1) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED);
        }

        $body = json_decode($request->getContent(), true);

        $type = $body['type'] ?? 'let-you-in';
        $userId = $body['userId'] ?? '';
        $title = $body['title'] ?? '';
        $text = $body['text'] ?? '';
        $parameters = $body['parameters'] ?? [];

        $user = $userRepository->find($userId);

        $notificationManager->sendNotifications(
            $user,
            new ReactNativePushNotification($type, $title, $text, $parameters)
        );

        return $this->handleResponse([], Response::HTTP_CREATED);
    }
}
