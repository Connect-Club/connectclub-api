<?php

namespace App\MessageHandler;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Entity\Notification\Notification;
use App\Message\SendNotificationMessageBatch;
use App\Repository\Notification\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;

final class SendNotificationMessageBatchHandler implements MessageHandlerInterface
{
    private ClientInterface $client;
    private Redis $redis;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;
    private Producer $producer;
    private NotificationRepository $notificationRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ClientInterface $client,
        Redis $redis,
        LoggerInterface $logger,
        Producer $producer,
        LockFactory $lockFactory,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->client = $client;
        $this->redis = $redis;
        $this->logger = $logger;
        $this->producer = $producer;
        $this->lockFactory = $lockFactory;
        $this->notificationRepository = $notificationRepository;
        $this->entityManager = $entityManager;
    }

    public function __invoke(SendNotificationMessageBatch $message)
    {
        if (extension_loaded('newrelic')) {
            newrelic_name_transaction('SendNotificationMessageBatchHandler');
            newrelic_start_transaction(ini_get("newrelic.appname"));
        }

        $request = [];

        $idempotentKey = $message->idempotentKey();

        $this->entityManager->beginTransaction();
        try {
            $notificationIds = [];
            foreach ($message->getBatch() as $notification) {
                if ($notification->notificationEntity) {
                    $notificationId = $notificationIds[] = $notification->notificationEntity->id->toString();
                } else {
                    $notificationId = '';
                }

                $notification->options['notificationId'] = $notificationId;

                $request[] = [
                    'platformCode' => $notification->platformType,
                    'deviceToken' => $notification->pushToken,
                    'messageText' => $notification->message,
                    'messageData' => $notification->options,
                    'notificationId' => $notificationId,
                ];
            }

            if ($notificationIds) {
                $this->notificationRepository->setStartProcessForNotifications($notificationIds);
            }

            $bulkInsert = $this->notificationRepository->bulkInsert();
            foreach ($message->getBatch() as $m) {
                if (!$m->notificationEntity instanceof Notification) {
                    continue;
                }

                $bulkInsert->insertEntity($m->notificationEntity);
            }
            $this->notificationRepository->executeBulkInsert($bulkInsert, true);

            $redisKey = 'batch_pushes_handled_'.$idempotentKey;

            if ($this->redis->get($redisKey) !== 1) {
                try {
                    $this->producer->publishToQueue(
                        'push_sender.v1.messages_notifications',
                        new AMQPMessage(json_encode([
                            'idempotentKey' => $idempotentKey,
                            'notifications' => $request,
                        ]))
                    );
                } catch (Throwable $exception) {
                    $this->logger->error($exception, ['exception' => $exception]);

                    $this->client->request('POST', $_ENV['NOTIFICATION_PUSHER_SERVER'].'/api/v1/notification/batch', [
                        'json' => $request,
                        'query' => [
                            'idempotentKey' => $idempotentKey,
                        ],
                        RequestOptions::CONNECT_TIMEOUT => 10,
                        RequestOptions::TIMEOUT => 10,
                        RequestOptions::READ_TIMEOUT => 10,
                    ]);
                }

                $this->redis->set($redisKey, 1);
                $this->redis->expire($redisKey, 3600 * 6);
            }

            if ($notificationIds) {
                $this->notificationRepository->setProcessedForNotifications($notificationIds);
            }

            $this->entityManager->commit();
        } catch (ConnectException $connectException) {
            if ($notificationIds) {
                $this->notificationRepository->setErrorForNotifications($notificationIds);
                $this->entityManager->commit();
            }
        } catch (Throwable $exception) {
            $this->entityManager->rollBack();

            throw $exception;
        }

        if (extension_loaded('newrelic')) {
            newrelic_end_transaction();
        }
    }
}
