<?php

namespace App\MessageHandler;

use App\Entity\Notification\Notification;
use App\Messenger\PushSenderMessage;
use App\Repository\Notification\NotificationRepository;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class PushSenderMessageHandler implements MessageHandlerInterface
{
    private NotificationRepository $notificationRepository;
    private LoggerInterface $logger;

    public function __construct(
        NotificationRepository $notificationRepository,
        LoggerInterface $logger
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->logger = $logger;
    }

    public function __invoke(PushSenderMessage $message)
    {
        $data = $message->getData()['data'] ?? [];

        $notificationId = $data['notification_id'] ?? '';
        if (!$notificationId || !Uuid::isValid($notificationId)) {
            $this->logger->error('Uuid is not valid', ['id' => $notificationId]);
            return;
        }

        $notification = $this->notificationRepository->find($notificationId);
        if (!$notification) {
            $this->logger->warning('Notification not found', ['id' => $notificationId]);
            return;
        }

        $state = $data['state'] ?? '';
        if (!$state) {
            $this->logger->error('State is incorrect', ['state' => $state]);
            return;
        }

        $notification->status = $state;
        $time = $data['time'] ?? 0;

        switch ($state) {
            case Notification::STATUS_ERROR:
                $notification->errorAt = $time;
                break;
            case Notification::STATUS_SEND:
                $notification->sendAt = $time;
                break;
            default:
                $this->logger->error('Not supported state '.$state);
        }

        $this->notificationRepository->save($notification);
    }
}
