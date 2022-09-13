<?php

namespace App\Tests\Command;

use App\Command\SendEventScheduleNotificationCommand;
use App\DataFixtures\AccessTokenFixture;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Entity\User\Device;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\SendNotificationMessage;
use App\Message\SendNotificationMessageBatch;
use App\Repository\Event\EventScheduleSubscriptionRepository;
use App\Service\Notification\NotificationManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Mockery;
use Ramsey\Uuid\Uuid;
use SplStack;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Console\Tester\CommandTester;
use App\Kernel;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SendEventScheduleNotificationCest extends BaseCest
{
    public function testExecuteEmpty(ApiTester $I)
    {
        ClockMock::register(__CLASS__);
        ClockMock::register(SendEventScheduleNotificationCommand::class);
        ClockMock::register(EventScheduleSubscriptionRepository::class);
        ClockMock::withClockMock(strtotime('20.02.2022 06:04:00'));

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                foreach ($alice->devices as $device) {
                    $manager->remove($device);
                }

                foreach ($mike->devices as $device) {
                    $manager->remove($device);
                }
                $manager->flush();



                $manager->persist(new Device(
                    Uuid::uuid4(),
                    $alice,
                    Device::TYPE_IOS_REACT,
                    'alice_token',
                    null,
                    'RU'
                ));

                $manager->persist(new Device(
                    Uuid::uuid4(),
                    $mike,
                    Device::TYPE_ANDROID_REACT,
                    'mike_token',
                    null,
                    'RU'
                ));

                $eventScheduleDailyMax = new EventSchedule($main, 'Event schedule 1', 1645337040 + 3600 * 24, '');
                //phpcs:ignore
                $eventScheduleDailyMin = new EventSchedule($main, 'Event schedule 2', 1645337040 - 1800 + 3600 * 24, '');
                $eventScheduleHourlyMax = new EventSchedule($main, 'Event schedule 3', 1645337040 + 3600, '');
                $eventScheduleHourlyMin = new EventSchedule($main, 'Event schedule 4', 1645337040 + 1800, '');
                //phpcs:ignore
                $eventScheduleOutOfRangeDaily = new EventSchedule($main, 'Event schedule 5', 1645337040 + 1 + 3600 * 24, '');
                //phpcs:ignore
                $eventScheduleOutOfRangeHourly = new EventSchedule($main, 'Event schedule 6', 1645337040 + 1800 - 1, '');

                $manager->persist($eventScheduleDailyMax);
                $manager->persist($eventScheduleDailyMin);
                $manager->persist($eventScheduleHourlyMax);
                $manager->persist($eventScheduleHourlyMin);
                $manager->persist($eventScheduleOutOfRangeDaily);
                $manager->persist($eventScheduleOutOfRangeHourly);

                $manager->persist(new EventScheduleSubscription($eventScheduleDailyMax, $alice));
                $manager->persist(new EventScheduleSubscription($eventScheduleDailyMin, $mike));
                $manager->persist(new EventScheduleSubscription($eventScheduleHourlyMax, $alice));
                $manager->persist(new EventScheduleSubscription($eventScheduleHourlyMin, $mike));
                $manager->persist(new EventScheduleSubscription($eventScheduleOutOfRangeDaily, $alice));
                $manager->persist(new EventScheduleSubscription($eventScheduleOutOfRangeHourly, $alice));

                $manager->flush();
            }
        }, false);

        $variants = [
            'Don\'t miss out on "Event schedule 1" on Monday, February 21 at 09:04 AM',
            'Don\'t miss out on "Event schedule 2" on Monday, February 21 at 05:34 AM',
            'Don\'t miss out on "Event schedule 3" at 10:04 AM',
            'Don\'t miss out on "Event schedule 4" at 06:34 AM',
        ];

        $bus = Mockery::mock(MessageBusInterface::class);
        $bus->shouldReceive('dispatch')->withArgs(function (SendNotificationMessageBatch $batch) use ($I, $variants) {
            foreach ($batch->getBatch() as $message) {
                $I->assertContains($message->message, $variants);
            }

            return true;
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)));
        $I->mockService(MessageBusInterface::class, $bus);

        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);

        $command = $application->find('SendEventScheduleNotification');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $I->assertEquals(1, 1);
    }
}
