<?php

namespace App\Tests\BulkInsert;

use App\DataFixtures\VideoRoomFixture;
use App\Entity\Activity\Activity;
use App\Entity\Activity\JoinRequestWasApprovedActivity;
use App\Entity\Activity\StartedVideoRoomActivity;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Activity\ActivityRepository;
use App\Repository\Activity\StartedVideoRoomActivityRepository;
use App\Repository\User\DeviceRepository;
use App\Repository\UserRepository;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\UserFixtureTrait;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;

class BulkInsertCest extends BaseCest
{
    const BASE_TIME = 100;

    public function testSpecificActivity(ApiTester $I): void
    {
        ClockMock::withClockMock(0);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            use UserFixtureTrait {
                createUser as private baseCreateUser;
            }

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;
                $userRepository = $this->getUserRepository();

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $manager->persist(new EventSchedule($main, 'Main Event Schedule', time(), 'Description'));
                $manager->persist(new EventSchedule($alice, 'Alice Event Schedule', time(), 'Description'));

                for ($i = 0; $i < 5; $i++) {
                    $users[] = $this->createUser("user-$i");
                }

                $manager->flush();
            }

            private function createUser(string $name): User
            {
                /** @var User $user */
                $user = $this->baseCreateUser($name);
                $user->about = 'test';

                return $user;
            }

            public function getDependencies(): array
            {
                return [
                    VideoRoomFixture::class
                ];
            }
        }, false);

        /** @var UserRepository $userRepository */
        $userRepository = $I->grabRepository(User::class);
        $users = $userRepository->findBy([
            'about' => 'test',
        ]);


        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_BOB_NAME,
            ]
        ]);

        /** @var StartedVideoRoomActivityRepository $startedVideoRoomActivityRepository */
        $startedVideoRoomActivityRepository = $I->grabRepository(StartedVideoRoomActivity::class);

        $bulkInsert = $startedVideoRoomActivityRepository->bulkInsert();

        for ($i = 0; $i < 10; $i++) {
            $recipient = $users[$i % count($users)];
            ClockMock::withClockMock($this->getCreatedAt($i));

            $activity = new StartedVideoRoomActivity(
                $videoRoom,
                $recipient,
                ...array_filter($users, fn($user) => !$user->equals($recipient))
            );
            $activity->readAt = $this->getReadAt($i);

            $bulkInsert->insertEntity($activity);
        }

        $startedVideoRoomActivityRepository->executeBulkInsert($bulkInsert);

        for ($i = 0; $i < 10; $i++) {
            $recipient = $users[$i % count($users)];

            $nestedUsers = array_map(
                fn($user) => $user->name,
                array_filter($users, fn($user) => !$user->equals($recipient))
            );

            /** @var StartedVideoRoomActivity $activity */
            $activity = $I->grabEntityFromRepository(StartedVideoRoomActivity::class, [
                'user' => $recipient,
                'videoRoom' => $videoRoom,
                'readAt' => $this->getReadAt($i),
                'createdAt' => $this->getCreatedAt($i),
            ]);

            $actualNestedUsers = [];
            foreach ($activity->nestedUsers as $nestedUser) {
                $actualNestedUsers[] = $nestedUser->name;
            }

            $I->assertEquals(
                uasort($nestedUsers, 'strcmp'),
                uasort($actualNestedUsers, 'strcmp')
            );
        }
    }

    public function testDifferentActivities(ApiTester $I): void
    {
        ClockMock::withClockMock(self::BASE_TIME);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            use UserFixtureTrait {
                createUser as private baseCreateUser;
            }

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;
                $userRepository = $this->getUserRepository();

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $manager->persist(new EventSchedule($main, 'Main Event Schedule', time(), 'Description'));
                $manager->persist(new EventSchedule($alice, 'Alice Event Schedule', time(), 'Description'));

                $manager->persist(new Club($main, 'Main Club'));

                for ($i = 0; $i < 5; $i++) {
                    $users[] = $this->createUser("user-$i");
                }

                $manager->flush();
            }

            private function createUser(string $name): User
            {
                $user = $this->baseCreateUser($name);
                $user->about = 'test';

                return $user;
            }

            public function getDependencies(): array
            {
                return [
                    VideoRoomFixture::class
                ];
            }
        }, false);

        /** @var UserRepository $userRepository */
        $userRepository = $I->grabRepository(User::class);
        $users = $userRepository->findBy([
            'about' => 'test',
        ]);

        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_BOB_NAME,
            ]
        ]);

        /** @var Club $mainClub */
        $mainClub = $I->grabEntityFromRepository(Club::class, ['title' => 'Main Club']);

        /** @var ActivityRepository $activityRepository */
        $activityRepository = $I->grabRepository(Activity::class);

        $bulkInsert = $activityRepository->bulkInsert();

        for ($i = 0; $i < 10; $i++) {
            $recipient = $users[$i % count($users)];
            ClockMock::withClockMock($this->getCreatedAt($i));

            if ($i % 2) {
                $activity = new StartedVideoRoomActivity(
                    $videoRoom,
                    $recipient,
                    ...array_filter($users, fn($user) => !$user->equals($recipient))
                );
                $activity->readAt = $this->getReadAt($i);
            } else {
                $activity = new JoinRequestWasApprovedActivity(
                    $mainClub,
                    ClubParticipant::ROLE_OWNER,
                    $recipient,
                    ...array_filter($users, fn($user) => !$user->equals($recipient))
                );
            }

            $bulkInsert->insertEntity($activity);
        }

        $activityRepository->executeBulkInsert($bulkInsert);

        for ($i = 0; $i < 10; $i++) {
            $recipient = $users[$i % count($users)];

            $nestedUsers = array_map(
                fn($user) => $user->name,
                array_filter($users, fn($user) => !$user->equals($recipient))
            );

            if ($i % 2) {
                /** @var StartedVideoRoomActivity $activity */
                $activity = $I->grabEntityFromRepository(StartedVideoRoomActivity::class, [
                    'user' => $recipient,
                    'videoRoom' => $videoRoom,
                    'readAt' => $this->getReadAt($i),
                    'createdAt' => $this->getCreatedAt($i),
                ]);
            } else {
                /** @var JoinRequestWasApprovedActivity $activity */
                $activity = $I->grabEntityFromRepository(JoinRequestWasApprovedActivity::class, [
                    'user' => $recipient,
                    'club' => $mainClub,
                    'readAt' => null,
                    'createdAt' => $this->getCreatedAt($i),
                ]);
            }

            $actualNestedUsers = [];
            foreach ($activity->nestedUsers as $nestedUser) {
                $actualNestedUsers[] = $nestedUser->name;
            }

            $I->assertEquals(
                uasort($nestedUsers, 'strcmp'),
                uasort($actualNestedUsers, 'strcmp')
            );
        }
    }

    public function testSimpleEntity(ApiTester $I): void
    {
        ClockMock::withClockMock(self::BASE_TIME);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            use UserFixtureTrait {
                createUser as private baseCreateUser;
            }

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                for ($i = 0; $i < 5; $i++) {
                    $users[] = $this->createUser("user-$i");
                }

                $manager->flush();
            }

            private function createUser(string $name): User
            {
                $user = $this->baseCreateUser($name);
                $user->about = 'test';

                return $user;
            }

            public function getDependencies(): array
            {
                return [
                    VideoRoomFixture::class
                ];
            }
        }, false);

        /** @var UserRepository $userRepository */
        $userRepository = $I->grabRepository(User::class);
        $users = $userRepository->findBy([
            'about' => 'test',
        ]);

        /** @var DeviceRepository $deviceRepository */
        $deviceRepository = $I->grabRepository(User\Device::class);
        $bulkInsert = $deviceRepository->bulkInsert();

        for ($i = 0; $i < 10; $i++) {
            $device = new User\Device(
                Uuid::uuid4(),
                $users[$i % count($users)],
                'android',
                "token-{$i}",
                '+1',
                'ru_RU'
            );

            $bulkInsert->insertEntity($device);
        }

        $deviceRepository->executeBulkInsert($bulkInsert);

        for ($i = 0; $i < 10; $i++) {
            $user = $users[$i % count($users)];

            $I->seeInRepository(User\Device::class, [
                'type' => 'android',
                'token' => "token-{$i}",
                'timeZone' => '+1',
                'locale' => 'ru_RU',
                'user' => $user,
            ]);
        }
    }

    private function getCreatedAt(int $i): int
    {
        return self::BASE_TIME + $i * 100;
    }

    private function getReadAt(int $i): int
    {
        return self::BASE_TIME + 100 * $i + 10;
    }
}
