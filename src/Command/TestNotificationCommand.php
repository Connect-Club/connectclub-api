<?php

namespace App\Command;

use App\Entity\User\Device;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Tests\BaseCest;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

class TestNotificationCommand extends Command
{
    protected static $defaultName = 'TestNotificationCommand';
    protected static $defaultDescription = 'Testing notification command';

    private NotificationManager $notificationManager;
    private UserRepository $userRepository;

    public function __construct(
        NotificationManager $notificationManager,
        UserRepository $userRepository
    ) {
        parent::__construct(self::$defaultName);

        $this->notificationManager = $notificationManager;
        $this->userRepository = $userRepository;
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

        $user = $this->userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

        if ($user->devices->count() < 1) {
            $this->userRepository->save(
                new Device(Uuid::uuid4()->toString(), $user, Device::TYPE_IOS_REACT, 'token', null, 'RU', 's')
            );
        }

        $this->notificationManager->setMode(NotificationManager::MODE_BATCH);
        for ($i = 0; $i < 20; $i++) {
            $this->notificationManager->sendNotifications(
                $user,
                new ReactNativePushNotification('type', 'Hi', 'Hello')
            );
        }
        $this->notificationManager->flushBatch();

        return Command::SUCCESS;
    }
}
