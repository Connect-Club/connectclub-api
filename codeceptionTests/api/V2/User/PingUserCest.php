<?php

namespace App\Tests\V2\User;

use App\Controller\ErrorCode;
use App\Entity\Community\Community;
use App\Entity\Follow\Follow;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Message\SendNotificationMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PingUserCest extends BaseCest
{
    public function pingTest(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $manager->persist(new User\Device(
                    Uuid::uuid4(),
                    $alice,
                    User\Device::TYPE_IOS_REACT,
                    'token',
                    null,
                    'RU'
                ));

                $manager->persist(new User\Device(
                    Uuid::uuid4(),
                    $bob,
                    User\Device::TYPE_ANDROID_REACT,
                    'token 2',
                    null,
                    'RU'
                ));

                $manager->persist(new Follow($main, $bob));
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($alice, $main));

                !$alice->isHasFollower($bob) && $manager->persist(new Follow($bob, $alice));
                !$bob->isHasFollower($alice) && $manager->persist(new Follow($alice, $bob));

                $manager->flush();
            }
        }, true);

        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $mikeId = $I->grabFromRepository(User::class, 'id', ['email' => self::MIKE_USER_EMAIL]);
        $bobId = $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$bobId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_USER_NOT_FOUND]]);


        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_IOS_REACT,
                //phpcs:ignore
                'main_user_name m. wants you to join. alice_user_name a. is talking about “Video room description” right now. Tap to listen'." ".'👉',
                $aliceId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, [$aliceId]);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$aliceId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);

        /** @var Community $community */
        $community = $I->grabEntityFromRepository(Community::class, [
            'name' => self::VIDEO_ROOM_TEST_NAME,
        ]);
        $invitedUsers = $community->videoRoom->invitedUsers;
        $I->assertCount(1, $invitedUsers);
        $I->assertSame(self::ALICE_USER_EMAIL, $invitedUsers[0]->getUsername());

        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_ANDROID_REACT,
                //phpcs:ignore
                'alice_user_name a. wants you to join. They are talking about “Video room description” right now. Tap to listen'." ".'👉',
                $bobId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$bobId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);


        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_IOS_REACT,
                //phpcs:ignore
                'main_user_name m. wants you to join. alice_user_name a.,bob_user_name b. and Mike M. are talking about “Video room description” right now. Tap to listen'." ".'👉',
                $aliceId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, [$aliceId, $bobId, $mikeId]);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$aliceId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);


        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_IOS_REACT,
                //phpcs:ignore
                'main_user_name m. wants you to join. alice_user_name a. and bob_user_name b. are talking about “Video room description” right now. Tap to listen'." ".'👉',
                $aliceId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, [$aliceId, $bobId]);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$aliceId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);


        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                for ($i = 0; $i < 10; $i++) {
                    $user = new User();
                    $user->email = 'user-'.$i.'@test.ru';
                    $user->name = 'user-'.$i;
                    $user->surname = 'surname-'.$i;
                    $user->username = 'username-'.$i;
                    $manager->persist($user);
                    $manager->persist(new Follow($main, $user));
                    $manager->persist(new Follow($user, $main));
                }

                $manager->flush();
            }
        }, true);

        $speakerIds = [];
        for ($i = 0; $i < 10; $i++) {
            $speakerIds[] = $I->grabFromRepository(User::class, 'id', ['username' => 'username-'.$i]);
        }

        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_IOS_REACT,
                //phpcs:ignore
                'main_user_name m. wants you to join. user-0 s.,user-1 s., user-2 s. and others are talking about “Video room description” right now. Tap to listen'." ".'👉',
                $aliceId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, $speakerIds);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$aliceId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $room = $manager->getRepository('App:VideoChat\VideoRoom')
                               ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);

                $room->community->description = null;
                $manager->persist($room);

                $manager->flush();
            }
        });

        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_IOS_REACT,
                //phpcs:ignore
                'main_user_name m. wants you to join. alice_user_name a. is talking right now. Tap to listen'." ".'👉',
                $aliceId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, [$aliceId]);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$aliceId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_IOS_REACT,
                //phpcs:ignore
                'main_user_name m. wants you to join. They are talking right now. Tap to listen'." ".'👉',
                $aliceId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, [$mainId]);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$aliceId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $room = $manager->getRepository('App:VideoChat\VideoRoom')
                    ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);

                $room->isPrivate = false;
                $room->community->description = null;
                $manager->persist($room);

                $manager->flush();
            }
        });

        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_ANDROID_REACT,
                //phpcs:ignore
                'alice_user_name a. wants you to join. main_user_name m. and Mike M. are talking right now. Tap to listen'." ".'👉',
                $bobId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, [$mainId, $mikeId]);
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$bobId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->mockService(
            MessageBusInterface::class,
            $this->generateMessagesBusMock(
                User\Device::TYPE_ANDROID_REACT,
                //phpcs:ignore
                'alice_user_name a. wants you to join. main_user_name m. is talking right now. Tap to listen'." ".'👉',
                $bobId,
                self::VIDEO_ROOM_TEST_NAME
            )
        );
        $this->generateDataTrackClientMock($I, [$mainId]);
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$bobId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    private function generateDataTrackClientMock(ApiTester $I, array $speakerNames)
    {
        $I->loadFixtures(new class($speakerNames) extends Fixture {
            private array $speakerNames;

            public function __construct(array $speakerNames)
            {
                $this->speakerNames = $speakerNames;
            }


            public function load(ObjectManager $manager)
            {
                $videoRoom = $manager->getRepository('App:VideoChat\VideoRoom')
                                     ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);

                $users = $manager->getRepository('App:User')->findUsersByIds($this->speakerNames);

                $activeMeeting = $videoRoom->getActiveMeeting();
                $activeMeeting && $manager->remove($activeMeeting);

                $meeting = new VideoMeeting($videoRoom, uniqid(), time());
                $manager->persist($meeting);

                foreach ($users as $user) {
                    $manager->persist(new VideoMeetingParticipant(
                        $meeting,
                        $user,
                        time(),
                        null,
                        true
                    ));
                }

                $manager->flush();
            }
        }, true);
    }

    private function generateMessagesBusMock(
        string $deviceType,
        string $expectedText,
        int $recipientId,
        string $videoRoomId
    ): MessageBusInterface {
        $busMock = Mockery::mock(MessageBusInterface::class);

        $busMock->shouldReceive('dispatch')
            ->withArgs(function ($message) use ($deviceType, $expectedText, $recipientId, $videoRoomId) {
                return $message instanceof SendNotificationMessage &&
                    $message->platformType === $deviceType &&
                    $message->message === $expectedText &&
                    $message->notificationEntity->recipientId === $recipientId &&
                    $message->notificationEntity->messageParameters['videoRoomId'] === $videoRoomId &&
                    $message->options['type'] === 'video-room';
            })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();

        return $busMock;
    }
}
