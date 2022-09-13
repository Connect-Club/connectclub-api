<?php

namespace App\MessageHandler;

use App\Message\SendNotificationMessage;
use App\Repository\Notification\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;

class SendNotificationMessageHandler implements MessageHandlerInterface
{
    private ClientInterface $client;
    private NotificationRepository $notificationRepository;
    private LockFactory $lockFactory;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private Redis $redis;

    public function __construct(
        ClientInterface $client,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        LockFactory $lockFactory,
        Redis $redis
    ) {
        $this->client = $client;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->notificationRepository = $notificationRepository;
        $this->lockFactory = $lockFactory;
        $this->redis = $redis;
    }

    public function __invoke(SendNotificationMessage $message)
    {
        $idempotentKey = $message->idempotentKey();

        $this->entityManager->beginTransaction();
        try {
            $notification = $message->notificationEntity;
            $notificationId = $message->notificationEntity->id->toString() ?? null;

            if ($notification) {
                if (!$notificationId || !$this->notificationRepository->find($notificationId)) {
                    $notification->startProcess();
                    $this->notificationRepository->save($notification);
                }
            }

            if ($notificationId) {
                $message->options['notificationId'] = $notificationId;
            }

            $redisKey = 'push_handled_'.$idempotentKey;

            if ($this->redis->get($redisKey) !== 1) {
                $this->client->request('POST', $_ENV['NOTIFICATION_PUSHER_SERVER'].'/api/v1/notification', [
                    RequestOptions::JSON => [
                        'platformCode' => $message->platformType,
                        'deviceToken' => $message->pushToken,
                        'messageText' => $message->message,
                        'messageData' => $message->options,
                        'notificationId' => $notificationId,
                    ],
                    RequestOptions::QUERY => [
                        'idempotentKey' => $idempotentKey
                    ],
                    RequestOptions::CONNECT_TIMEOUT => 10,
                    RequestOptions::TIMEOUT => 10,
                    RequestOptions::READ_TIMEOUT => 10,
                ]);

                $this->redis->set($redisKey, 1);
                $this->redis->expire($redisKey, 3600 * 6);
            }

            if ($notification) {
                $notification->doneProcess();
                $this->notificationRepository->save($notification);
            }

            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }
}
