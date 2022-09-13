<?php

namespace App\Command;

use App\Doctrine\ConnectionSpecificResult;
use App\Entity\Activity\Activity;
use App\Entity\Event\EventScheduleSubscription;
use App\Repository\Event\EventScheduleSubscriptionRepository;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\Notification\TimeSpecificZoneTranslationParameter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class SendEventScheduleNotificationCommand extends Command
{
    protected static $defaultName = 'SendEventScheduleNotification';
    protected static $defaultDescription = 'Send event schedules daily, hourly notifications';

    private EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository;
    private NotificationManager $notificationManager;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository,
        NotificationManager $notificationManager,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct(self::$defaultName);

        $this->eventScheduleSubscriptionRepository = $eventScheduleSubscriptionRepository;
        $this->notificationManager = $notificationManager;
        $this->entityManager = $entityManager;
    }


    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->entityManager->getConnection()->beginTransaction();

        try {
            $this->handleNotifications(
                $subscriptions = $this->eventScheduleSubscriptionRepository->findEventScheduleSubscriptions(
                    $this->entityManager,
                    EventScheduleSubscriptionRepository::MODE_HOURLY
                ),
                'notifications.event_schedule_starts_1_hour_title',
                'notifications.event_schedule_starts_1_hour',
                EventScheduleSubscriptionRepository::MODE_HOURLY,
                'h:i A',
            );

            if ($subscriptions->getResult()) {
                $this->eventScheduleSubscriptionRepository->updateSubscriptions(
                    $this->entityManager,
                    array_map(
                        fn(EventScheduleSubscription $s) => $s->id->toString(),
                        $subscriptions->getResult()
                    ),
                    EventScheduleSubscriptionRepository::MODE_HOURLY
                );
            }

            $this->handleNotifications(
                $subscriptions = $this->eventScheduleSubscriptionRepository->findEventScheduleSubscriptions(
                    $this->entityManager,
                    EventScheduleSubscriptionRepository::MODE_DAILY
                ),
                'notifications.event_schedule_starts_24_hours_title',
                'notifications.event_schedule_starts_24_hours',
                EventScheduleSubscriptionRepository::MODE_DAILY
            );

            if ($subscriptions->getResult()) {
                $this->eventScheduleSubscriptionRepository->updateSubscriptions(
                    $this->entityManager,
                    array_map(
                        fn(EventScheduleSubscription $s) => $s->id->toString(),
                        $subscriptions->getResult()
                    ),
                    EventScheduleSubscriptionRepository::MODE_DAILY
                );
            }

            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        return Command::SUCCESS;
    }

    private function handleNotifications(
        ConnectionSpecificResult $result,
        string $title,
        string $body,
        int $mode,
        $format = 'l, F d \a\t h:i A'
    ) {
        /** @var EventScheduleSubscription[] $subscriptions */
        $subscriptions = $result->getResult();

        $this->notificationManager->setMode(NotificationManager::MODE_BATCH);
        $this->notificationManager->prepareDeviceTokensForParticipants(array_map(
            fn(EventScheduleSubscription $subscription) => $subscription->user->id,
            $subscriptions
        ));

        $specificKey = 'event-schedule-'.$mode == EventScheduleSubscriptionRepository::MODE_DAILY ? 'daily' : 'hourly';

        foreach ($subscriptions as $subscription) {
            $this->notificationManager->sendNotifications(
                $subscription->user,
                new ReactNativePushNotification(
                    'event-schedule',
                    $title,
                    $body,
                    [
                        'eventScheduleId' => $subscription->eventSchedule->id->toString(),
                        PushNotification::PARAMETER_SPECIFIC_KEY => $specificKey,
                    ],
                    [
                        '%eventName%' => $subscription->eventSchedule->name,
                        '%time%' => new TimeSpecificZoneTranslationParameter(
                            $subscription->eventSchedule->dateTime,
                            $format
                        ),
                    ]
                )
            );
        }

        $this->notificationManager->flushBatch();
    }
}
