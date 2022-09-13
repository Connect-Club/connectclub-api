<?php

namespace App\Tests\Event;

use App\Controller\ErrorCode;
use App\Entity\Activity\InvitePrivateVideoRoomActivity;
use App\Entity\Community\Community;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\Follow\Follow;
use App\Entity\Notification\Notification;
use App\Entity\User;
use App\Entity\User\Device;
use App\Entity\VideoChat\VideoRoom;
use App\Message\SendNotificationMessage;
use App\Service\EventManager;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\VideoRoomNotifier;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\CallVideoRoomFixture;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class EventCest extends BaseCest
{
    public function createPrivateRoomTest(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $manager->persist(new Device(
                    Uuid::uuid4(),
                    $alice,
                    Device::TYPE_IOS_REACT,
                    'token',
                    null,
                    'RU'
                ));
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($alice, $main));

                $manager->flush();
            }
        }, true);

        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

//        "%relatedUserName% wants you to join the room and speak privately about “%meetingName%”. Tap to join 👉"
        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                //phpcs:ignore
                $message->message == 'main_user_name m. wants you to join the room and speak privately. '."Tap to join 👉" &&
                $message->options['type'] == 'video-room' &&
                !empty($message->options['videoRoomId']) &&
                !empty($message->options['videoRoomPassword']);
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();
        $I->mockService(MessageBusInterface::class, $busMock);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/event/private/'.$aliceId);
        $I->seeResponseCodeIs(HttpCode::OK);

        $name = $I->grabDataFromResponseByJsonPath('$.response.roomId')[0];
        $password = $I->grabDataFromResponseByJsonPath('$.response.roomPass')[0];

        /** @var InvitePrivateVideoRoomActivity $invitePrivateVideoRoomActivity */
        $invitePrivateVideoRoomActivity = $I->grabEntityFromRepository(InvitePrivateVideoRoomActivity::class, [
            'user' => [
                'email' => self::ALICE_USER_EMAIL
            ],
            'videoRoom' => [
                'community' => [
                    'name' => $name,
                    'password' => $password,
                ]
            ]
        ]);

        $I->assertCount(1, $invitePrivateVideoRoomActivity->nestedUsers);
        $I->assertEquals(self::MAIN_USER_EMAIL, $invitePrivateVideoRoomActivity->nestedUsers->first()->email);

        $videoRoom = $invitePrivateVideoRoomActivity->videoRoom;

        $I->assertCount(2, $videoRoom->invitedUsers);
        $I->assertFalse($videoRoom->invitedUsers->filter(fn($u) => $u->email == self::MAIN_USER_EMAIL)->isEmpty());
        $I->assertFalse($videoRoom->invitedUsers->filter(fn($u) => $u->email == self::ALICE_USER_EMAIL)->isEmpty());
    }

    /**
     * @dataProvider makePublicDataProvider
     */
    public function makePublic(ApiTester $I, Example $example)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $community = $manager->getRepository(Community::class)->findOneBy([
                    'name' => BaseCest::VIDEO_ROOM_TEST_NAME
                ]);
                $community->videoRoom->isPrivate = true;

                $main = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $community->addParticipant($main, CommunityParticipant::ROLE_ADMIN);
                $community->addParticipant($alice, CommunityParticipant::ROLE_MODERATOR);

                $manager->persist($community);
                $manager->flush();
            }
        });

        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_TEST_NAME
            ]
        ]);
        $I->assertTrue($videoRoom->isPrivate);

        $eventManager = Mockery::mock(EventManager::class);
        $videoRoomNotifier = Mockery::mock(VideoRoomNotifier::class);

        if ($example['expectedNotification']) {
            /** @var User $main */
            $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
            $videoRoom->eventSchedule = new EventSchedule($main, 'Test', time(), null);
            $I->persistEntity($videoRoom->eventSchedule);
            $I->flushToDatabase();

            $eventManager->shouldReceive('sendNotifications')
                ->with($videoRoom->eventSchedule)
                ->once();
        } else {
            $eventManager->shouldReceive('sendNotifications')
                ->times(0);
        }

        $videoRoomNotifier->shouldReceive('notifyStarted')
            ->with($videoRoom)
            ->once();

        $I->mockService(EventManager::class, $eventManager);
        $I->mockService(VideoRoomNotifier::class, $videoRoomNotifier);

        $I->amBearerAuthenticated($example['accessToken']);
        $I->sendPost('/v1/event/'.self::VIDEO_ROOM_TEST_NAME.'/public');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->assertFalse($videoRoom->isPrivate);
    }

    protected function makePublicDataProvider(): \Generator
    {
        yield [
            'accessToken' => self::ALICE_ACCESS_TOKEN,
            'expectedNotification' => false,
        ];
        yield [
            'accessToken' => self::MAIN_ACCESS_TOKEN,
            'expectedNotification' => false,
        ];
        yield [
            'accessToken' => self::ALICE_ACCESS_TOKEN,
            'expectedNotification' => true,
        ];
        yield [
            'accessToken' => self::MAIN_ACCESS_TOKEN,
            'expectedNotification' => true,
        ];
    }

    public function testMakePublicNotModerator(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $community = $manager->getRepository(Community::class)->findOneBy([
                    'name' => BaseCest::VIDEO_ROOM_TEST_NAME
                ]);
                $community->videoRoom->isPrivate = true;

                $bob = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $community->addParticipant($bob);

                $manager->persist($community);
                $manager->flush();
            }
        });

        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_TEST_NAME
            ]
        ]);
        $I->assertTrue($videoRoom->isPrivate);

        $eventManager = Mockery::mock(EventManager::class);
        $I->mockService(EventManager::class, $eventManager);

        $eventManager->shouldReceive('sendNotifications')
            ->times(0);

        $videoRoomNotifier = Mockery::mock(VideoRoomNotifier::class);
        $I->mockService(VideoRoomNotifier::class, $videoRoomNotifier);

        $videoRoomNotifier->shouldReceive('notifyStarted')
            ->times(0);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost('/v1/event/'.self::VIDEO_ROOM_TEST_NAME.'/public');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_VIDEO_ROOM_NOT_FOUND]]);

        $I->assertTrue($videoRoom->isPrivate);
    }

    public function testAcceptInviteByOwner(ApiTester $I): void
    {
        $I->loadFixtures(new CallVideoRoomFixture());

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPost('/v1/event/invite/' . BaseCest::VIDEO_ROOM_TEST_NAME . '/accept');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function testAcceptInviteByCallee(ApiTester $I): void
    {
        $I->loadFixtures(new CallVideoRoomFixture());

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);

        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_TEST_NAME,
            ],
        ]);

        $notificationManager = Mockery::mock(NotificationManager::class);
        $notificationManager->shouldReceive('sendNotifications')
            ->withArgs(
                function (
                    User $actualUser,
                    ReactNativePushNotification $actualNotification
                ) use (
                    $I,
                    $videoRoom
                ) {
                    $I->assertEquals($videoRoom->community->owner, $actualUser);

                    /** @var User $expectedSender */
                    $expectedSender = $I->grabEntityFromRepository(User::class, [
                        'email' => BaseCest::BOB_USER_EMAIL,
                    ]);

                    $this->assertInviteAcceptedNotification($I, $expectedSender, $videoRoom, $actualNotification);

                    return true;
                }
            )
            ->once();
        $I->mockService(NotificationManager::class, $notificationManager);

        $I->sendPost('/v1/event/invite/' . BaseCest::VIDEO_ROOM_TEST_NAME . '/accept');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function testAcceptInviteAccessDenied(ApiTester $I): void
    {
        $I->loadFixtures(new CallVideoRoomFixture());

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        $I->sendPost('/v1/event/invite/' . BaseCest::VIDEO_ROOM_TEST_NAME . '/accept');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function testCancelInviteByOwner(ApiTester $I): void
    {
        $I->loadFixtures(new CallVideoRoomFixture());

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $notificationManager = Mockery::mock(NotificationManager::class);
        $notificationManager->shouldReceive('sendNotifications')
            ->withArgs(function (User $recipient, ReactNativePushNotification $actualNotification) use ($I) {
                $I->assertEquals(self::BOB_USER_EMAIL, $recipient->email);

                /** @var User $expectedInitiator */
                $expectedInitiator = $I->grabEntityFromRepository(User::class, [
                    'email' => BaseCest::MAIN_USER_EMAIL,
                ]);

                $this->assertInviteCanceledNotification($I, $expectedInitiator, $actualNotification);

                return true;
            })
            ->once();
        $I->mockService(NotificationManager::class, $notificationManager);

        $I->sendPost('/v1/event/invite/' . BaseCest::VIDEO_ROOM_TEST_NAME . '/cancel');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function testCancelInviteByCallee(ApiTester $I): void
    {
        $I->loadFixtures(new CallVideoRoomFixture());

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);

        $notificationManager = Mockery::mock(NotificationManager::class);
        $notificationManager->shouldReceive('sendNotifications')
            ->withArgs(function (User $recipient, ReactNativePushNotification $actualNotification) use ($I) {
                $I->assertEquals(self::MAIN_USER_EMAIL, $recipient->email);

                /** @var User $expectedInitiator */
                $expectedInitiator = $I->grabEntityFromRepository(User::class, [
                    'email' => BaseCest::BOB_USER_EMAIL,
                ]);

                $this->assertInviteCanceledNotification($I, $expectedInitiator, $actualNotification);

                return true;
            })
            ->once();
        $I->mockService(NotificationManager::class, $notificationManager);

        $I->sendPost('/v1/event/invite/' . BaseCest::VIDEO_ROOM_TEST_NAME . '/cancel');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function testCancelInviteAccessDenied(ApiTester $I): void
    {
        $I->loadFixtures(new CallVideoRoomFixture());

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        $I->sendPost('/v1/event/invite/' . BaseCest::VIDEO_ROOM_TEST_NAME . '/cancel');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    private function assertInviteAcceptedNotification(
        ApiTester $I,
        User $expectedInitiator,
        VideoRoom $videoRoom,
        ReactNativePushNotification $actualNotification
    ): void {
        $message = $actualNotification->getMessage();

        $I->assertEquals(self::VIDEO_ROOM_TEST_NAME, $message->getMessageParameter('inviteId'));
        $I->assertEquals($videoRoom->id, $message->getMessageParameter('videoRoomId'));
        $I->assertEquals($videoRoom->community->password, $message->getMessageParameter('videoRoomPassword'));
        $I->assertNull($message->getMessageParameter('title'));
        $I->assertEquals($expectedInitiator->id, $message->getMessageParameter('initiator_id'));
        $I->assertEquals('invite-accepted', $message->getMessageParameter('specific_key'));
        $I->assertEquals('invite-accepted', $message->getMessageParameter('type'));
        $I->assertNull($message->getMessage());
    }

    private function assertInviteCanceledNotification(
        ApiTester $I,
        User $expectedInitiator,
        ReactNativePushNotification $actualNotification
    ): void {
        $message = $actualNotification->getMessage();

        $I->assertEquals(self::VIDEO_ROOM_TEST_NAME, $message->getMessageParameter('inviteId'));
        $I->assertNull($message->getMessageParameter('title'));
        $I->assertEquals($expectedInitiator->id, $message->getMessageParameter('initiator_id'));
        $I->assertEquals('invite-cancelled', $message->getMessageParameter('specific_key'));
        $I->assertEquals('invite-cancelled', $message->getMessageParameter('type'));
        $I->assertEquals('notifications.invite-cancelled', $message->getMessage());
    }
}
