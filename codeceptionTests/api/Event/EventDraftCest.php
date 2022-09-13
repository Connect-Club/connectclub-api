<?php

namespace App\Tests\Event;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Event\EventDraft;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Interest\Interest;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\EventDraftFixture;
use App\Tests\Fixture\FriendshipFixtureTrait;
use Codeception\Util\HttpCode;
use Doctrine\Persistence\ObjectManager;
use Mockery\MockInterface;
use Ramsey\Uuid\Uuid;

class EventDraftCest extends BaseCest
{
    const CREATED_VIDEO_ROOM_RESPONSE_JSON = [
        'id' => 'string',
        'title' => 'string|null',
        'participants' => 'array',
        'online' => 'integer',
        'speaking' => 'integer',
        'roomId' => 'string',
        'roomPass' => 'string',
        'withSpeakers' => 'boolean',
        'isPrivate' => 'boolean',
        'speakers' => 'array',
        'interests' => 'array',
        'listeners' => 'array',
        'isCoHost' => 'boolean',
        'draftType' => 'string',
        'subscriptionId' => 'string',
        'club' => 'array|null',
    ];

    public function testDrafts(ApiTester $I)
    {
        $I->loadFixtures(new class extends EventDraftFixture {
            public function load(ObjectManager $manager)
            {
                parent::load($manager);

                $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $eventSchedule = new EventSchedule($user, 'Main event schedule', time(), '');
                foreach ([$alice, $bob, $mike] as $user) {
                    $eventSchedule->participants->add(new EventScheduleParticipant($eventSchedule, $user));
                }
                $eventSchedule->id = Uuid::fromString('733060de-2e52-4b09-919d-01075837160b');
                $manager->persist($eventSchedule);

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/event-draft');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'id' => 'b13d048e-e594-49f6-a932-efdf02853335',
            'type' => EventDraft::TYPE_SMALL_BROADCASTING
        ]);

        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/event');
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseMatchesJsonTypeStrict(self::CREATED_VIDEO_ROOM_RESPONSE_JSON);
        $I->seeResponseContainsJson([
            'response' => [
                'title' => null,
                'participants' => [],
                'online' => 0,
                'speaking' => 0,
                'withSpeakers' => true,
                'isPrivate' => false,
                'club' => null,
            ],
        ]);

        $body = json_encode(['title' => 'Desc']);
        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/event', $body);
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseMatchesJsonTypeStrict(self::CREATED_VIDEO_ROOM_RESPONSE_JSON);
        $I->seeResponseContainsJson([
            'response' => [
                'title' => 'Desc',
                'participants' => [],
                'online' => 0,
                'speaking' => 0,
                'withSpeakers' => true,
            ],
        ]);

        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/event', json_encode([
            'eventScheduleId' => '733060de-2e52-4b09-919d-01075837160b'
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseMatchesJsonTypeStrict(self::CREATED_VIDEO_ROOM_RESPONSE_JSON);
        $I->seeResponseContainsJson([
            'response' => [
                'title' => 'Main event schedule',
                'participants' => [],
                'online' => 0,
                'speaking' => 0,
                'withSpeakers' => true,
            ],
        ]);

        //Repeat
        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/event', json_encode([
            'eventScheduleId' => '733060de-2e52-4b09-919d-01075837160b'
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseMatchesJsonTypeStrict(self::CREATED_VIDEO_ROOM_RESPONSE_JSON);
        $I->seeResponseContainsJson([
            'response' => [
                'title' => 'Main event schedule',
                'participants' => [],
                'online' => 0,
                'speaking' => 0,
                'withSpeakers' => true,
            ],
        ]);

        $I->clearEntityManager();
        $id = $I->grabDataFromResponseByJsonPath('$.response.id')[0];
        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, ['id' => $id]);
        $I->assertNotNull($videoRoom->eventSchedule);
        $I->assertNotNull($videoRoom->eventSchedule->videoRoom);
        $I->assertEquals('733060de-2e52-4b09-919d-01075837160b', $videoRoom->eventSchedule->id);
        $I->assertCount(4, $videoRoom->community->participants);

        /** @var User $main */
        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        /** @var User $alice */
        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);
        /** @var User $bob */
        $bob = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]);
        /** @var User $mike */
        $mike = $I->grabEntityFromRepository(User::class, ['email' => self::MIKE_USER_EMAIL]);

        $community = $videoRoom->community;
        $I->assertEquals(CommunityParticipant::ROLE_ADMIN, $community->getParticipant($main)->role);
        $I->assertEquals(CommunityParticipant::ROLE_ADMIN, $community->getParticipant($alice)->role);
        $I->assertEquals(CommunityParticipant::ROLE_ADMIN, $community->getParticipant($bob)->role);
        $I->assertEquals(CommunityParticipant::ROLE_ADMIN, $community->getParticipant($mike)->role);
    }

    public function testCall(ApiTester $I)
    {
        $I->loadFixtures(new class extends EventDraftFixture {
            use FriendshipFixtureTrait;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                parent::load($manager);

                $userRepository = $manager->getRepository(User::class);

                $user = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $this->makeFriends($user, $alice);
            }
        });

        /** @var User $alice */
        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $notificationManager = \Mockery::spy(NotificationManager::class);
        $I->mockService(NotificationManager::class, $notificationManager);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/call', json_encode([
            'title' => 'Test',
            'userId' => (string) $alice->id,
            'language' => $I->grabFromRepository(User\Language::class, 'id', [
                'code' => 'RU',
            ]),
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $community = $this->assertCommunityReturned($I, 'Test');

        $I->seeResponseContainsJson([
            'response' => [
                'message' => "Great! Just hang out until they reply. If they're free, we'll create a new room for you.",
            ],
        ]);
        $this->assertInviteSent($I, $notificationManager, $alice, $community);
    }

    public function testCallWithoutTitle(ApiTester $I)
    {
        $I->loadFixtures(new class extends EventDraftFixture {
            use FriendshipFixtureTrait;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                parent::load($manager);

                $userRepository = $manager->getRepository(User::class);

                $user = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $this->makeFriends($user, $alice);
            }
        });

        /** @var User $alice */
        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $notificationManager = \Mockery::spy(NotificationManager::class);
        $I->mockService(NotificationManager::class, $notificationManager);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/call', json_encode([
            'userId' => (string) $alice->id,
            'language' => $I->grabFromRepository(User\Language::class, 'id', [
                'code' => 'RU',
            ]),
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $community = $this->assertCommunityReturned($I, null);

        $I->seeResponseContainsJson([
            'response' => [
                'message' => "Great! Just hang out until they reply. If they're free, we'll create a new room for you.",
            ],
        ]);

        $this->assertInviteSent($I, $notificationManager, $alice, $community);
    }

    public function testCallValidation(ApiTester $I)
    {
        $I->loadFixtures(new class extends EventDraftFixture {
            use FriendshipFixtureTrait;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                parent::load($manager);

                $userRepository = $manager->getRepository(User::class);

                $user = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $this->makeFriends($user, $alice);
            }
        });

        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $notificationManager = \Mockery::spy(NotificationManager::class);
        $I->mockService(NotificationManager::class, $notificationManager);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/call', json_encode([
            'title' => 'Test',
            'userId' => 'test',
            'language' => $I->grabFromRepository(User\Language::class, 'id', [
                'code' => 'RU',
            ]),
        ]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        $I->sendPost('/v1/event-draft/b13d048e-e594-49f6-a932-efdf02853335/call', json_encode([
            'userId' => (string) $alice->id,
            'language' => 100000,
        ]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    private function assertCommunityReturned(ApiTester $I, ?string $expectedDescription): Community
    {
        $inviteId = $I->grabDataFromResponseByJsonPath('$.response.inviteId')[0];

        /** @var Community $community */
        $community = $I->grabEntityFromRepository(Community::class, ['name' => $inviteId]);
        $I->assertNotNull($community);
        $I->assertEquals($expectedDescription, $community->description);

        return $community;
    }

    private function assertInviteSent(
        ApiTester $I,
        MockInterface $notificationManager,
        User $recipient,
        Community $community
    ) {
        $notificationManager->shouldHaveReceived('sendNotifications')
            ->withArgs(
                function (
                    User $user,
                    ReactNativePushNotification $actualNotification
                ) use (
                    $recipient,
                    $I,
                    $community
                ) {
                    if ($recipient->id !== $user->id) {
                        return false;
                    }

                    $this->assertInvitePrivateNotification($I, $community->videoRoom, $actualNotification);

                    return true;
                }
            )
            ->once();
    }

    private function assertInvitePrivateNotification(
        ApiTester $I,
        VideoRoom $expectedVideoRoom,
        ReactNativePushNotification $actualNotification
    ): void {
        $message = $actualNotification->getMessage();
        $I->assertEquals($expectedVideoRoom->id, $message->getMessageParameter('videoRoomId'));
        $I->assertEquals(
            $expectedVideoRoom->community->password,
            $message->getMessageParameter('videoRoomPassword')
        );
        $I->assertEquals($expectedVideoRoom->community->name, $message->getMessageParameter('inviteId'));
        $I->assertEquals(1, $message->getMessageParameter('initiator_id'));
        $I->assertEquals('invite-private', $message->getMessageParameter('specific_key'));
        $I->assertEquals('invite-private', $message->getMessageParameter('type'));

        if ($expectedVideoRoom->community->description === null) {
            $I->assertEquals(
                'main_user_name m. wants you to join the room and speak privately. Tap to join 👉',
                $message->getMessage()
            );
        } else {
            $I->assertEquals(
                //phpcs:ignore
                'main_user_name m. wants you to join the room and speak privately about “'.$expectedVideoRoom->community->description.'”. Tap to join 👉',
                $message->getMessage()
            );
        }
    }
}
