<?php

namespace App\Tests\Club;

use App\Client\ElasticSearchClientBuilder;
use App\Client\GoogleCloudStorageClient;
use App\Entity\Activity\IntroActivity;
use App\Entity\Activity\JoinRequestWasApprovedActivity;
use App\Entity\Activity\NewJoinRequestActivity;
use App\Entity\Club\Club;
use App\Entity\Club\ClubInvite;
use App\Entity\Club\ClubParticipant;
use App\Entity\Club\JoinRequest;
use App\Entity\Follow\Follow;
use App\Entity\Interest\Interest;
use App\Entity\Invite\Invite;
use App\Entity\Photo\Image;
use App\Entity\User;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\InviteAllNetworkToClubMessage;
use App\Service\MatchingClient;
use App\Service\Notification\Message;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\FriendshipFixtureTrait;
use App\Tests\Fixture\UserFixtureTrait;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use PHPUnit\Framework\AssertionFailedError;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ClubCest extends BaseCest
{
    public function testRelevantClubs(ApiTester $I)
    {
        $matchingClientMock = Mockery::mock(MatchingClient::class);
        $matchingClientMock->shouldReceive('findClubMatchingForUser')->andReturn([
            'data' => [
                ['id' => '84ac6e6d-4db8-4013-b078-5aac1827ba0e'],
                ['id' => 'eb9bb96a-2429-4d69-a13e-8a752f09b804'],
                ['id' => '91b66d16-a916-4010-acf9-84ae3954fbe4'],
            ],
            'lastValue' => null,
        ]);
        $I->mockService(MatchingClient::class, $matchingClientMock);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $ids = [
                    '91b66d16-a916-4010-acf9-84ae3954fbe4',
                    'eb9bb96a-2429-4d69-a13e-8a752f09b804',
                    '84ac6e6d-4db8-4013-b078-5aac1827ba0e',
                ];

                $userRepository = $manager->getRepository('App:User');
                $interestRepository = $manager->getRepository('App:Interest\Interest');

                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $writingInterest = $interestRepository->findOneBy(['name' => '📖 Writing']);

                foreach (['📖 Writing club', '📖 Read books', '📖 Harry potter books fun club'] as $book) {
                    $club = new Club($main, $book);
                    $club->id = Uuid::fromString(array_pop($ids));
                    $club->addInterest($writingInterest);
                    $manager->persist($club);
                }

                $bob->addInterest($writingInterest);
                $manager->persist($bob);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);

        $I->sendGet('/v1/club/relevant');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(3, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        foreach (['📖 Writing club', '📖 Read books', '📖 Harry potter books fun club'] as $i => $clubName) {
            $I->assertEquals($clubName, $I->grabDataFromResponseByJsonPath('$.response.items['.$i.'].title')[0]);
        }
    }

    public function testMyClubs(ApiTester $I)
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                for ($i = 0; $i < 30; $i++) {
                    $club = new Club($i % 2 === 0 ? $main : $bob, 'Club '.$i);
                    if (!$club->owner->equals($main)) {
                        $club->participants->add(
                            new ClubParticipant($club, $main, $bob, ClubParticipant::ROLE_MODERATOR)
                        );
                    }
                    $manager->persist($club);
                }
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club/my?limit=5');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function testAllClubsWithQuery(ApiTester $I)
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $clubOutOfCriteria = new Club($user, 'My Club 0');
                $manager->persist($clubOutOfCriteria);

                $clubWithName = new Club($user, 'club with name (Test)');
                $clubWithName->slug = 'something else';
                $manager->persist($clubWithName);

                $clubWithSlug = new Club($user, 'club with slug');
                $clubWithSlug->slug = '555-test-44';
                $manager->persist($clubWithSlug);

                $clubWithDescription = new Club($user, 'club with description');
                $clubWithDescription->description = 'this is tEst club';
                $manager->persist($clubWithDescription);

                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club?query=teST');
        $I->seeResponseCodeIs(HttpCode::OK);
        $responseJson = json_decode($I->grabResponse(), true);
        $I->assertSame(3, $responseJson['response']['totalCount']);
        $actualClubNames = array_map(
            static fn(array $item): string => $item['title'],
            $responseJson['response']['items']
        );
        $expectedClubNames = [
            'club with slug',
            'club with name (Test)',
            'club with description',
        ];
        $I->assertSame($expectedClubNames, $actualClubNames);
    }

    public function testAllClubsWithoutQuery(ApiTester $I)
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $clubOutOfCriteria = new Club($user, 'My Club 0');
                $manager->persist($clubOutOfCriteria);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club');
        $I->seeResponseCodeIs(HttpCode::OK);
        $responseJson = json_decode($I->grabResponse(), true);
        $I->assertSame(1, $responseJson['response']['totalCount']);
        $actualClubNames = array_map(
            static fn(array $item): string => $item['title'],
            $responseJson['response']['items']
        );
        $expectedClubNames = ['My Club 0'];
        $I->assertSame($expectedClubNames, $actualClubNames);
    }

    public function testCreateClub(ApiTester $I)
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));

        $bus = Mockery::mock(MessageBusInterface::class);
        $bus->shouldReceive('dispatch')
            ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $bus);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $googleCloudMock = Mockery::mock(GoogleCloudStorageClient::class);
        $googleCloudMock->shouldReceive('uploadImage')->andReturn(['object' => 'processed.png']);
        $I->mockService(GoogleCloudStorageClient::class, $googleCloudMock);

        $I->sendPOST('/v1/upload', [], [
            'image' => [
                'name' => 'video_room_background.png',
                'type' => 'image/png',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize(codecept_data_dir('video_room_background.png')),
                'tmp_name' => codecept_data_dir('video_room_background.png')
            ]
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $imageId = $I->grabDataFromResponseByJsonPath('$.response.id')[0];

        $en = $I->grabFromRepository(User\Language::class, 'id', ['code' => 'EN']);
        $ge = $I->grabFromRepository(User\Language::class, 'id', ['code' => 'GE']);
        $ru = $I->grabFromRepository(User\Language::class, 'id', ['code' => 'RU']);

        $I->sendPost('/v1/club', json_encode([
            'title' => 'Main Owner Club Новый клуб NFT',
            'description' => 'Main description',
            'imageId' => $imageId,
            'interests' => [
                ['id' => $en],
                ['id' => $ge],
                ['id' => $ru],
            ]
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'title' => 'Main Owner Club Новый клуб NFT',
                'description' => 'Main description',
                'countParticipants' => 1,
                'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/.png',
                'interests' => [
                ],
                'slug' => 'main-owner-club-novyj-klub-nft',
            ]
        ]);

        $clubId = $I->grabDataFromResponseByJsonPath('$.response.id')[0];

        //Get club info from main owner
        $I->sendGet('/v1/club/'.$clubId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'id' => $clubId,
                'joinRequestStatus' => null,
                'clubRole' => 'owner',
                'title' => 'Main Owner Club Новый клуб NFT',
                'description' => 'Main description',
                'countParticipants' => 1,
                'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/.png',
                'slug' => 'main-owner-club-novyj-klub-nft',
            ],
        ]);

        /** @var User $mike */
        $mike = $I->grabEntityFromRepository(User::class, ['email' => self::MIKE_USER_EMAIL]);
        $I->assertEquals(User::STATE_NOT_INVITED, $mike->state);

        //Get club info from mike as guest
        $I->amBearerAuthenticated(self::MIKE_ACCESS_TOKEN);
        $I->sendGet('/v1/club/'.$clubId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'id' => $clubId,
                'joinRequestStatus' => null,
                'clubRole' => null,
                'title' => 'Main Owner Club Новый клуб NFT',
                'description' => 'Main description',
                'countParticipants' => 1,
                'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/.png',
            ],
        ]);

        //Send join request from mike
        $I->sendPost('/v1/club/'.$clubId.'/join');
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseContainsJson([
            'response' => [
                'joinRequestStatus' => 'moderation',
            ]
        ]);

        //List all join requests by owner main
        $joinRequestId = $I->grabDataFromResponseByJsonPath('$.response.joinRequestId')[0];
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v1/club/'.$clubId.'/join-requests');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(
            1,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0]
        );
        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'joinRequestId' => $joinRequestId,
                        'user' => [
                            'isFollowing' => false,
                            'isFollows' => false,
                            'followers' => 0,
                            'following' => 0,
                            'name' => 'Mike',
                            'surname' => 'Mike',
                            'displayName' => 'Mike Mike',
                        ],
                    ],
                ],
                'lastValue' => null,
            ]
        ]);

        /** @var Club $club */
        $club = $I->grabEntityFromRepository(Club::class, ['id' => $clubId]);

        $notificationManager = Mockery::mock(NotificationManager::class);
        $notificationManager->shouldReceive('sendNotifications')
            ->once()
            ->withArgs(function (User $recipient, ReactNativePushNotification $actualNotification) use ($I, $club) {
                $I->assertEquals(BaseCest::MIKE_USER_EMAIL, $recipient->email);

                $main = $this->findUser($I, BaseCest::MAIN_USER_EMAIL);

                $message = $actualNotification->getMessage();
                $this->assertMessageParams($I, [
                    'clubId' => $club->id,
                    'clubTitle' => $club->title,
                    'type' => 'join-request-was-approved',
                    'specific_key' => 'join-request-was-approved',
                    'title' => 'Welcome to “'.$club->title.'”',
                    'initiator_id' => $main->id,
                    PushNotification::PARAMETER_IMAGE => null,
                    PushNotification::PARAMETER_SECOND_IMAGE => 'https://pics.connect.lol/300x300/.png'
                ], $message);

                $I->assertEquals(
                    //phpcs:ignore
                    'main_user_name m. (creator) approved you request',
                    $message->getMessage()
                );

                return true;
            });
        $I->mockService(NotificationManager::class, $notificationManager);

        //Approve alice by main admin
        $I->sendPost('/v1/club/'.$joinRequestId.'/approve');
        $I->seeResponseCodeIs(HttpCode::OK);

        /** @var Club $club */
        $club = $I->grabEntityFromRepository(Club::class, ['id' => $clubId]);
        $I->assertEquals(999, $club->freeInvites);

        $I->seeInRepository(JoinRequestWasApprovedActivity::class, [
            'club' => $club,
            'user' => $I->grabEntityFromRepository(User::class, ['email' => BaseCest::MIKE_USER_EMAIL]),
        ]);

        $I->seeInRepository(IntroActivity::class, [
            'user' => $I->grabEntityFromRepository(User::class, ['email' => BaseCest::MIKE_USER_EMAIL]),
        ]);

        $I->seeInRepository(Invite::class, [
            'club' => $club,
            'author' => $I->grabEntityFromRepository(User::class, ['email' => BaseCest::MAIN_USER_EMAIL]),
            'registeredUser' => $I->grabEntityFromRepository(User::class, ['email' => BaseCest::MIKE_USER_EMAIL]),
        ]);

        $joinRequest = $I->grabEntityFromRepository(JoinRequest::class, [
            'id' => $joinRequestId,
        ]);

        $I->dontSeeInRepository(NewJoinRequestActivity::class, [
            'joinRequest' => $joinRequest,
        ]);

        $mike = $I->grabEntityFromRepository(User::class, ['email' => self::MIKE_USER_EMAIL]);
        $I->assertEquals(User::STATE_INVITED, $mike->state);

        //Check actual join requests is empty
        $I->sendGet('/v1/club/'.$clubId.'/join-requests');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(
            0,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0]
        );

        //Check alice member
        $I->amBearerAuthenticated(self::MIKE_ACCESS_TOKEN);
        $I->sendGet('/v1/club/'.$clubId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'joinRequestStatus' => 'approved',
                'clubRole' => 'member',
            ],
        ]);

        $notificationManager->shouldReceive('setMode')
            ->with(NotificationManager::MODE_BATCH)
            ->once();
        $notificationManager->shouldReceive('flushBatch')
            ->once();
        $notificationManager->shouldReceive('sendNotifications')
            ->once();

        //Send join request from bob
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost('/v1/club/'.$clubId.'/join');
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseContainsJson([
            'response' => [
                'joinRequestStatus' => 'moderation',
            ]
        ]);

        $joinRequestId = $I->grabDataFromResponseByJsonPath('$.response.joinRequestId')[0];

        //Check actual join requests is not empty
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club/'.$clubId.'/join-requests');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(
            1,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0]
        );

        //Cancel join request of bob by main admin
        $I->sendPost('/v1/club/'.$joinRequestId.'/cancel');
        $I->seeResponseCodeIs(HttpCode::OK);

        //Check bob response after cancel your request by main owner
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendGet('/v1/club/'.$clubId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'joinRequestStatus' => null,
                'clubRole' => null,
            ]
        ]);

        //Check actual join requests is empty
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club/'.$clubId.'/join-requests');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(
            0,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0]
        );

        $I->comment('Test get info about club as anonymous');
        $I->amBearerAuthenticated(null);
        $I->sendGet('/v1/club/'.$clubId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'id' => $clubId,
                'joinRequestStatus' => null,
                'clubRole' => null,
                'title' => 'Main Owner Club Новый клуб NFT',
                'description' => 'Main description',
                'countParticipants' => 1,
                'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/.png',
            ]
        ]);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);
                $mike->state = User::STATE_VERIFIED;
                $manager->persist($mike);
                $manager->flush();
            }
        }, true);

        $I->comment('Test get info about club as anonymous');
        $I->amBearerAuthenticated(null);
        $I->sendGet('/v1/club/'.$clubId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'id' => $clubId,
                'countParticipants' => 2,
            ]
        ]);

        $I->sendGet('/v1/club/5b860e93-e0dd-4e03-9ebd-111908c5dd8d');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club/');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'title' => 'Main Owner Club Новый клуб NFT',
                    ],
                ],
            ]
        ]);
    }

    public function testInfo(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            use UserFixtureTrait;

            public function load(ObjectManager $manager): void
            {
                $this->entityManager = $manager;

                $main = $this->getUserRepository()->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $manager->persist(new ClubParticipant($mainClub, $this->createUser('User-1'), $main));
                $manager->persist(new ClubParticipant($mainClub, $this->createUser('User-2'), $main));
                $manager->persist(new ClubParticipant($mainClub, $this->createUser('User-3'), $main));
                $manager->persist(new ClubParticipant($mainClub, $this->createUser('User-4'), $main));
                $manager->persist(
                    new ClubParticipant($mainClub, $this->createUser('User-5', User::STATE_BANNED), $main)
                );
                $manager->persist(new ClubParticipant($mainClub, $this->createDeletedUser('User-6'), $main));

                $manager->flush();
            }
        });

        $clubId = $I->grabFromRepository(Club::class, 'id', [
            'title' => 'Main Club',
        ]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet("/v1/club/$clubId");

        $I->seeResponseContainsJson([
            'response' => [
                'id' => $clubId,
                'clubRole' => 'owner',
                'joinRequestStatus' => null,
                'title' => 'Main Club',
                'description' => null,
                'countParticipants' => 5,
                'avatar' => null,
            ]
        ]);

        $this->assertMemberCount($I, 3);
    }

    public function testMembers(ApiTester $I): void
    {
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            use FriendshipFixtureTrait;

            public function load(ObjectManager $manager): void
            {
                $this->entityManager = $manager;

                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $secondMainClub = new Club($main, 'Second Main Club');
                $manager->persist($secondMainClub);

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $manager->persist(new ClubParticipant($mainClub, $bob, $main));
                $manager->persist(new ClubParticipant($mainClub, $alice, $main, ClubParticipant::ROLE_MODERATOR));

                $manager->persist(new Follow($bob, $alice));
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($bob, $main));

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $mainClub = $I->grabEntityFromRepository(Club::class, [
            'title' => 'Main Club',
        ]);

        $I->sendGet("/v1/club/$mainClub->id/members");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'name' => self::MAIN_USER_NAME,
                        'isFollowing' => false,
                        'isFollows' => false,
                        'clubRole' => ClubParticipant::ROLE_OWNER,
                    ],
                    [
                        'name' => self::ALICE_USER_NAME,
                        'isFollowing' => true,
                        'isFollows' => false,
                        'clubRole' => ClubParticipant::ROLE_MODERATOR,
                    ],
                    [
                        'name' => self::BOB_USER_NAME,
                        'isFollowing' => false,
                        'isFollows' => true,
                        'clubRole' => ClubParticipant::ROLE_MEMBER,
                    ],
                ],
            ],
        ]);

        $I->sendGet("/v1/club/$mainClub->id/members", [
            'limit' => 1,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $lastValue = $this->assertItemNames($I, [
            self::MAIN_USER_NAME,
        ]);

        $I->sendGet("/v1/club/$mainClub->id/members", [
            'limit' => 1,
            'lastValue' => $lastValue,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $lastValue = $this->assertItemNames($I, [
            self::ALICE_USER_NAME,
        ]);

        $I->sendGet("/v1/club/$mainClub->id/members", [
            'limit' => 1,
            'lastValue' => $lastValue,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $lastValue = $this->assertItemNames($I, [
            self::BOB_USER_NAME,
        ]);
        $I->assertNull($lastValue);

        // requests with 'search'
        $alice = $this->findUser($I, BaseCest::ALICE_USER_EMAIL);

        // search for existing user
        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(1, 'alice', [$alice->getId()])
        );

        $I->sendGet("/v1/club/$mainClub->id/members?search=alice", [
            'limit' => 10,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items'));
        $I->assertSame(BaseCest::ALICE_USER_NAME, $I->grabDataFromResponseByJsonPath('$.response.items')[0][0]['name']);

        // search for unexisting user
        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(1, 'alice111', [])
        );

        $I->sendGet("/v1/club/$mainClub->id/members?search=alice111", [
            'limit' => 10,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
    }

    public function testUpdate(ApiTester $I)
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            use FriendshipFixtureTrait;

            public function load(ObjectManager $manager): void
            {
                $this->entityManager = $manager;

                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $mainClub = new Club($main, 'Main Club');

                $photography = $manager->getRepository(Interest::class)->findOneBy(['name' => '📷 Photography']);
                $mainClub->addInterest($photography);

                $manager->persist($mainClub);

                $manager->persist(new Club($main, 'Second Main Club'));

                $manager->persist(new Image(
                    'test-bucket',
                    'test.png',
                    'test.png',
                    $main
                ));

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $mainClub = $this->findClub($I, 'Main Club');

        /** @var Interest $design */
        $design = $I->grabEntityFromRepository(Interest::class, [
            'name' => '🎨 Design'
        ]);

        /** @var Image $avatar */
        $avatar = $I->grabEntityFromRepository(Image::class, ['originalName' => 'test.png']);

        $I->sendPatch("/v1/club/$mainClub->id", json_encode([
            'description' => 'Description',
            'title' => 'Main Club Changed',
            'interests' => [
                [
                    'id' => $design->id,
                    'name' => $design->name,
                ],
            ],
            'imageId' => $avatar->getId(),
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        /** @var Club $changedMainClub */
        $changedMainClub = $I->grabEntityFromRepository(Club::class, [
            'title' => 'Main Club Changed',
            'description' => 'Description',
            'avatar' => $avatar,
        ]);

        $this->assertInterests($I, ['🎨 Design'], $changedMainClub->interests);
    }

    public function testCurrentUserJoinRequests(ApiTester $I): void
    {
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $secondMainClub = new Club($main, 'Second Main Club');
                $manager->persist($secondMainClub);
                $manager->persist(new JoinRequest($secondMainClub, $alice));

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $joinRequest = new JoinRequest($mainClub, $alice);
                $joinRequest->status = JoinRequest::STATUS_APPROVED;
                $manager->persist($joinRequest);
                $manager->persist(new JoinRequest($mainClub, $bob));

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        $mainClub = $this->findClub($I, 'Main Club');
        $secondMainClub = $this->findClub($I, 'Second Main Club');

        $I->sendGet('/v1/club/join-requests');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(
            2,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0]
        );
        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'clubId' => $secondMainClub->id->toString(),
                        'joinRequestStatus' => 'moderation',
                    ],
                    [
                        'clubId' => $mainClub->id->toString(),
                        'joinRequestStatus' => 'approved',
                    ],
                ],
                'lastValue' => null,
            ]
        ]);
    }

    public function testJoin(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            private EntityManagerInterface $entityManager;

            public function load(ObjectManager $manager): void
            {
                $this->entityManager = $manager;

                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $secondMainClub = new Club($main, 'Second Main Club');
                $manager->persist($secondMainClub);
                $manager->persist(new JoinRequest($secondMainClub, $alice));

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $this->createModerator($mainClub, $alice);

                $manager->flush();
            }

            private function createModerator(Club $club, User $user): void
            {
                $participant = new ClubParticipant($club, $user, $club->owner, ClubParticipant::ROLE_MODERATOR);
                $this->entityManager->persist($participant);
            }
        });

        $mainClub = $this->findClub($I, 'Main Club');


        $main = $this->findUser($I, BaseCest::MAIN_USER_EMAIL);
        $alice = $this->findUser($I, BaseCest::ALICE_USER_EMAIL);
        $bob = $this->findUser($I, BaseCest::BOB_USER_EMAIL);

        $notificationManager = Mockery::spy(NotificationManager::class);
        $I->mockService(NotificationManager::class, $notificationManager);

        $notificationManager->shouldReceive('setMode')
            ->with(NotificationManager::MODE_BATCH)
            ->once();

        //Send join request from bob
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/join");
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseContainsJson([
            'response' => [
                'joinRequestStatus' => 'moderation',
            ]
        ]);

        /** @var JoinRequest $joinRequest */
        $joinRequest = $I->grabEntityFromRepository(JoinRequest::class, [
            'id' => $I->grabDataFromResponseByJsonPath('$.response.joinRequestId'),
        ]);
        $this->assertNewJoinRequestNotificationSent(
            $I,
            $notificationManager,
            $joinRequest,
            $main,
            $bob
        );

        $this->assertNewJoinRequestNotificationSent(
            $I,
            $notificationManager,
            $joinRequest,
            $alice,
            $bob
        );

        $I->seeInRepository(NewJoinRequestActivity::class, [
            'joinRequest' => $joinRequest,
            'user' => $main,
        ]);
        $I->seeInRepository(NewJoinRequestActivity::class, [
            'joinRequest' => $joinRequest,
            'user' => $alice,
        ]);

        $I->assertEquals($joinRequest->club->id, $mainClub->id);
        $I->assertEquals($joinRequest->author->id, $bob->id);
        $I->assertEquals($joinRequest->status, 'moderation');
    }

    public function testParticipants(ApiTester $I)
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                for ($i = 0; $i < 20; $i++) {
                    $mainClub = new Club($main, 'Main Club '.$i);
                    $manager->persist($mainClub);

                    $participant = new ClubParticipant($mainClub, $alice, $main);
                    $participant->joinedAt = 1650376748 + $i;

                    $manager->persist($participant);
                }

                $manager->flush();
            }
        });

        $alice = $this->findUser($I, self::ALICE_USER_EMAIL);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club/'.$alice->id.'/participant?limit=10');
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponse('lastValue');
        $I->assertNotNull($lastValue);

        $I->assertEquals('main-club-10', $I->grabDataFromResponse('items[0].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[0].clubRole'));
        $I->assertEquals('main-club-11', $I->grabDataFromResponse('items[1].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[1].clubRole'));
        $I->assertEquals('main-club-12', $I->grabDataFromResponse('items[2].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[2].clubRole'));
        $I->assertEquals('main-club-13', $I->grabDataFromResponse('items[3].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[3].clubRole'));
        $I->assertEquals('main-club-14', $I->grabDataFromResponse('items[4].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[4].clubRole'));
        $I->assertEquals('main-club-15', $I->grabDataFromResponse('items[5].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[0].clubRole'));
        $I->assertEquals('main-club-16', $I->grabDataFromResponse('items[6].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[1].clubRole'));
        $I->assertEquals('main-club-17', $I->grabDataFromResponse('items[7].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[2].clubRole'));
        $I->assertEquals('main-club-18', $I->grabDataFromResponse('items[8].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[3].clubRole'));
        $I->assertEquals('main-club-19', $I->grabDataFromResponse('items[9].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[4].clubRole'));

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/club/'.$alice->id.'/participant?limit=10&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponse('lastValue');
        $I->assertNull($lastValue);

        $I->assertEquals('main-club-0', $I->grabDataFromResponse('items[0].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[0].clubRole'));
        $I->assertEquals('main-club-1', $I->grabDataFromResponse('items[1].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[1].clubRole'));
        $I->assertEquals('main-club-2', $I->grabDataFromResponse('items[2].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[2].clubRole'));
        $I->assertEquals('main-club-3', $I->grabDataFromResponse('items[3].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[3].clubRole'));
        $I->assertEquals('main-club-4', $I->grabDataFromResponse('items[4].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[4].clubRole'));
        $I->assertEquals('main-club-5', $I->grabDataFromResponse('items[5].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[0].clubRole'));
        $I->assertEquals('main-club-6', $I->grabDataFromResponse('items[6].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[1].clubRole'));
        $I->assertEquals('main-club-7', $I->grabDataFromResponse('items[7].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[2].clubRole'));
        $I->assertEquals('main-club-8', $I->grabDataFromResponse('items[8].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[3].clubRole'));
        $I->assertEquals('main-club-9', $I->grabDataFromResponse('items[9].slug'));
        $I->assertEquals('owner', $I->grabDataFromResponse('items[4].clubRole'));
        $I->assertNull($lastValue);
    }

    public function testLeave(ApiTester $I): void
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $secondMainClub = new Club($main, 'Second Main Club');
                $manager->persist($secondMainClub);

                $manager->persist(new ClubParticipant($mainClub, $alice, $main, ClubParticipant::ROLE_MODERATOR));
                $manager->persist(new ClubParticipant($secondMainClub, $alice, $main));
                $manager->persist(new ClubParticipant($mainClub, $bob, $main));

                $manager->flush();
            }
        });

        $mainClub = $this->findClub($I, 'Main Club');

        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::MAIN_USER_EMAIL],
        ]);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::BOB_USER_EMAIL],
        ]);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::MIKE_USER_EMAIL],
        ]);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/leave");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/leave");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/leave");
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['club_owner_cannot_be_removed']);

        $I->amBearerAuthenticated(self::MIKE_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/leave");
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['not_found_in_participants_club']);

        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::MAIN_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::BOB_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => ['email' => self::MIKE_USER_EMAIL],
        ]);
    }

    public function testLeaveAndTryToJoinAgain(ApiTester $I)
    {
        $bus = Mockery::mock(MessageBusInterface::class);
        $bus->shouldReceive('dispatch')
            ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $bus);

        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $club = new Club($main, 'Main Club');
                $manager->persist($club);
                $manager->persist(new ClubInvite($club, $alice, $main));

                $manager->flush();
            }
        });

        $club = $this->findClub($I, 'Main Club');

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        // Send join request
        $I->sendPost('/v1/club/' . $club->id . '/join');
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->seeInRepository(ClubParticipant::class, [
            'club' => $club,
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);

        // Send leave request
        $I->sendPost("/v1/club/$club->id/leave");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $club,
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);

        // Send join request again
        $I->sendPost('/v1/club/' . $club->id . '/join');

        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $club,
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);
        $I->seeInRepository(JoinRequest::class, [
            'club' => $club,
            'author' => ['email' => self::ALICE_USER_EMAIL],
            'status' => JoinRequest::STATUS_MODERATION,
        ]);
    }

    public function testModerator(ApiTester $I): void
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $secondMainClub = new Club($main, 'Second Main Club');
                $manager->persist($secondMainClub);

                $manager->persist(new ClubParticipant($mainClub, $alice, $main));
                $manager->persist(new ClubParticipant($secondMainClub, $alice, $main));
                $manager->persist(new ClubParticipant($mainClub, $bob, $main));

                $manager->flush();
            }
        });

        $mainClub = $this->findClub($I, 'Main Club');
        $alice = $this->findUser($I, self::ALICE_USER_EMAIL);
        $bob = $this->findUser($I, self::BOB_USER_EMAIL);

        $I->sendPost("/v1/club/$mainClub->id/$alice->id/moderator");
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        $I->sendPost("/v1/club/$mainClub->id/$alice->id/moderator");
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $alice,
            'role' => ClubParticipant::ROLE_MEMBER,
        ]);
        $I->sendPost("/v1/club/$mainClub->id/$alice->id/moderator");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $alice,
            'role' => ClubParticipant::ROLE_MODERATOR,
        ]);
        $I->sendDelete("/v1/club/$mainClub->id/$alice->id/moderator");
        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $alice,
            'role' => ClubParticipant::ROLE_MODERATOR,
        ]);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $alice,
            'role' => ClubParticipant::ROLE_MEMBER,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $this->findClub($I, 'Second Main Club'),
            'user' => $alice,
            'role' => ClubParticipant::ROLE_MEMBER,
        ]);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $bob,
            'role' => ClubParticipant::ROLE_MEMBER,
        ]);

        // moderator assign/revoke another moderator
        $I->sendPost("/v1/club/$mainClub->id/$alice->id/moderator");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/$bob->id/moderator");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $bob,
            'role' => ClubParticipant::ROLE_MODERATOR,
        ]);
        $I->sendDelete("/v1/club/$mainClub->id/$bob->id/moderator");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $bob,
            'role' => ClubParticipant::ROLE_MODERATOR,
        ]);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $bob,
            'role' => ClubParticipant::ROLE_MEMBER,
        ]);
    }

    public function testOwner(ApiTester $I): void
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                $manager->persist(new ClubParticipant($mainClub, $alice, $main));
                $manager->persist(new ClubParticipant($mainClub, $bob, $main));

                $manager->flush();
            }
        });

        $mainClub = $this->findClub($I, 'Main Club');
        $alice = $this->findUser($I, self::ALICE_USER_EMAIL);
        $bob = $this->findUser($I, self::BOB_USER_EMAIL);
        $main = $this->findUser($I, self::MAIN_USER_EMAIL);

        $I->sendPost("/v1/club/$mainClub->id/$alice->id/moderator");
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/$alice->id/moderator");
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->assertEquals(BaseCest::MAIN_USER_EMAIL, $mainClub->owner->email);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $main,
            'role' => ClubParticipant::ROLE_OWNER,
        ]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost("/v1/club/$mainClub->id/$alice->id/owner");
        $I->seeResponseCodeIs(HttpCode::OK);

        $mainClub = $this->findClub($I, 'Main Club');
        $I->assertEquals(BaseCest::ALICE_USER_EMAIL, $mainClub->owner->email);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $alice,
            'role' => ClubParticipant::ROLE_OWNER,
        ]);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $main,
            'role' => ClubParticipant::ROLE_MEMBER,
        ]);
        $I->seeInRepository(ClubParticipant::class, [
            'club' => $mainClub,
            'user' => $bob,
            'role' => ClubParticipant::ROLE_MEMBER,
        ]);
    }

    public function testInviteAllFromNetwork(ApiTester $I)
    {
        $I->mockService(MatchingClient::class, Mockery::spy(MatchingClient::class));

        $bus = Mockery::mock(MessageBusInterface::class);
        $bus->shouldReceive('dispatch')
            ->andReturn(new Envelope(Mockery::mock(InviteAllNetworkToClubMessage::class)));
        $I->mockService(MessageBusInterface::class, $bus);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mainClub = new Club($main, 'Main Club');
                $manager->persist($mainClub);

                foreach ([$alice, $mike, $bob] as $friend) {
                    $manager->persist(new Follow($friend, $main));
                    $manager->persist(new Follow($main, $friend));
                }

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $mainClub = $this->findClub($I, 'Main Club');

        $I->sendGet('/v1/follow/friends?forInviteClub='.$mainClub->id->toString());
        $I->seeResponseCodeIs(HttpCode::OK);
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(2, $items);
        $I->seeResponseContainsJson([
            ['name' => 'bob_user_name'],
            ['name' => 'alice_user_name'],
        ]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/club/'.$mainClub->id->toString().'/all');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v1/follow/friends?forInviteClub='.$mainClub->id->toString());
        $I->seeResponseCodeIs(HttpCode::OK);
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(0, $items);
    }

    private function findClub(ApiTester $I, string $title): Club
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $I->grabEntityFromRepository(Club::class, [
            'title' => $title,
        ]);
    }

    private function findUser(ApiTester $I, string $email): User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $I->grabEntityFromRepository(User::class, [
            'email' => $email,
        ]);
    }

    /**
     * @param string[] $expectedInterests
     * @param Interest[]|Collection|null $actualInterests
     */
    private function assertInterests(ApiTester $I, array $expectedInterests, ?Collection $actualInterests): void
    {
        $I->assertNotNull($actualInterests);

        $actualInterests = array_map(fn(Interest $interest) => $interest->name, $actualInterests->toArray());

        $I->assertEquals($expectedInterests, $actualInterests);
    }

    private function assertItemNames(ApiTester $I, array $expectedNames): ?string
    {
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertNotNull($items);
        $I->assertEquals($expectedNames, array_column($items, 'name'));

        return $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
    }

    private function assertMemberCount(ApiTester $I, int $expectedCount): void
    {
        $I->assertCount($expectedCount, $I->grabDataFromResponseByJsonPath('$.response.members[*]'));
    }

    /**
     * @param NotificationManager|Mockery\MockInterface $notificationManager
     */
    private function assertNewJoinRequestNotificationSent(
        ApiTester $I,
        NotificationManager $notificationManager,
        JoinRequest $joinRequest,
        User $expectedRecipient,
        User $expectedSender
    ): void {
        $notificationManager->shouldHaveReceived('sendNotifications')
            ->withArgs(function (
                User $recipient,
                ReactNativePushNotification $actualNotification
            ) use (
                $I,
                $joinRequest,
                $expectedRecipient,
                $expectedSender
            ) {
                try {
                    $I->assertEquals($expectedRecipient->email, $recipient->email, 'Email not equals');

                    $message = $actualNotification->getMessage();

                    $this->assertMessageParams($I, [
                        'specific_key' => 'new-join-request',
                        'initiator_id' => $expectedSender->id,
                        'joinRequestId' => $joinRequest->id->toString(),
                        'type' => 'new-join-request',
                        'title' => 'New request to join the club',
                        'clubId' => $joinRequest->club->id->toString(),
                        PushNotification::PARAMETER_IMAGE => $expectedSender->getAvatarSrc(300, 300),
                        PushNotification::PARAMETER_SECOND_IMAGE => $joinRequest->club->avatar ?
                            $joinRequest->club->avatar->getResizerUrl(300, 300) : null,
                    ], $message);

                    $I->assertEquals(
                        "{$expectedSender->getFullNameOrId(true)} wants to join “{$joinRequest->club->title}”. Tap to review 👉", // phpcs:ignore
                        $message->getMessage(),
                        'Not equals message'
                    );
                } catch (AssertionFailedError $exception) {
                    return false;
                }

                return true;
            })
            ->once();
    }

    private function assertMessageParams(ApiTester $I, array $expectedParams, Message $message): void
    {
        $actualParams = $message->getMessageParameters();

        uksort($actualParams, fn($param1, $param2) => $param1 <=> $param2);
        uksort($expectedParams, fn($param1, $param2) => $param1 <=> $param2);

        foreach ($actualParams as &$param) {
            if ($param instanceof Uuid) {
                $param = $param->toString();
            }
        }
        unset($param);

        foreach ($expectedParams as &$param) {
            if ($param instanceof Uuid) {
                $param = $param->toString();
            }
        }
        unset($param);

        $diff1 = array_diff($expectedParams, $actualParams);
        $diff2 = array_diff($actualParams, $expectedParams);

        $I->assertEquals(
            $expectedParams,
            $actualParams,
            'Parameters not equals '.var_export($diff1, true).' '.var_export($diff2, true)
        );
    }
}
