<?php

namespace App\Tests\User;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Client\ElasticSearchClientBuilder;
use App\DataFixtures\AccessTokenFixture;
use App\DataFixtures\VideoRoomFixture;
use App\Entity\Activity\NewFollowerActivity;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Follow\Follow;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\FriendshipFixtureTrait;
use App\Tests\Fixture\UserFixtureTrait;
use App\Tests\V2\User\UserCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Ramsey\Uuid\Uuid;

class FollowCest extends BaseCest
{
    public function followAcceptance(ApiTester $I)
    {
        $mainId = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL])->id;
        $aliceId = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL])->id;
        $bobId = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL])->id;

        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/follow/subscribe', json_encode([$bobId, $aliceId, $aliceId, $mainId]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $activities = $I->grabEntitiesFromRepository(NewFollowerActivity::class, [
            'user' => ['email' => self::ALICE_USER_EMAIL]
        ]);
        $I->assertCount(1, $activities);
        $I->assertEquals(self::MAIN_USER_EMAIL, $activities[0]->nestedUsers->first()->email);

        $activities = $I->grabEntitiesFromRepository(NewFollowerActivity::class, [
            'user' => ['email' => self::BOB_USER_EMAIL]
        ]);
        $I->assertCount(1, $activities);
        $I->assertEquals(self::MAIN_USER_EMAIL, $activities[0]->nestedUsers->first()->email);

        $I->seeInRepository(Follow::class, [
            'follower' => ['email' => self::MAIN_USER_EMAIL],
            'user' => ['email' => self::BOB_USER_EMAIL]
        ]);
        $I->seeInRepository(Follow::class, [
            'follower' => ['email' => self::MAIN_USER_EMAIL],
            'user' => ['email' => self::ALICE_USER_EMAIL]
        ]);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost('/v1/follow/subscribe', json_encode([$mainId]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(Follow::class, [
            'follower' => ['email' => self::BOB_USER_EMAIL],
            'user' => ['email' => self::MAIN_USER_EMAIL]
        ]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/'.$mainId.'/followers');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict([
            'items' => [
                [
                    'id' => 'string',
                    'displayName' => 'string',
                    'about' => 'string',
                    'username' => 'string|null',
                    'isDeleted' => 'boolean',
                    'createdAt' => 'integer',
                    'name' => 'string',
                    'surname' => 'string',
                    'avatar' => 'string|null',
                    'isFollowing' => 'boolean',
                    'isFollows' => 'boolean',
                    'online' => 'boolean',
                    'lastSeen' => 'integer',
                    'badges' => 'array',
                    'shortBio' => 'string|null',
                    'longBio' => 'string|null',
                    'twitter' => 'string|null',
                    'instagram' => 'string|null',
                    'linkedin' => 'string|null',
                ]
            ],
            'lastValue' => 'integer|null',
        ]);

        $I->seeResponseContainsJson([
            'name' => 'bob_user_name',
            'surname' => 'bob_user_surname'
        ]);

        $I->sendPost('/v1/follow/'.$bobId.'/unsubscribe');
        $I->sendPost('/v1/follow/'.$aliceId.'/unsubscribe');

        $I->dontSeeInRepository(Follow::class, [
            'follower' => ['email' => self::MAIN_USER_EMAIL],
            'user' => ['email' => self::BOB_USER_EMAIL]
        ]);
        $I->dontSeeInRepository(Follow::class, [
            'follower' => ['email' => self::MAIN_USER_EMAIL],
            'user' => ['email' => self::ALICE_USER_EMAIL]
        ]);

        //Activities don't removed after unsubscribe
        $activities = $I->grabEntitiesFromRepository(NewFollowerActivity::class, [
            'user' => ['email' => self::ALICE_USER_EMAIL]
        ]);
        $I->assertCount(1, $activities);
        $I->assertEquals(self::MAIN_USER_EMAIL, $activities[0]->nestedUsers->first()->email);

        //After second subscribe activities not duplicates check
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/follow/subscribe', json_encode([$aliceId]));
        $I->seeResponseCodeIs(HttpCode::OK);

        //Activities don't removed after unsubscribe
        $activities = $I->grabEntitiesFromRepository(NewFollowerActivity::class, [
            'user' => ['email' => self::ALICE_USER_EMAIL]
        ]);
        $I->assertCount(1, $activities);
        $I->assertEquals(self::MAIN_USER_EMAIL, $activities[0]->nestedUsers->first()->email);
    }

    public function testPaginationByCursor(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                for ($i = 0; $i < 60; $i++) {
                    $user = new User();
                    $user->email = 'user-'.$i.'@gmail.com';
                    $user->name = 'name-'.$i;
                    $user->surname = 'surname-'.$i;
                    $user->state = User::STATE_VERIFIED;

                    $manager->persist($user);

                    $this->setReference('user-'.$i, $user);
                }

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                for ($i = 0; $i < 30; $i++) {
                    $manager->persist(new Follow($this->getReference('user-'.$i), $alice));
                }

                for ($i = 30; $i < 50; $i++) {
                    $manager->persist(new Follow($this->getReference('user-'.$i), $bob));
                }

                for ($i = 50; $i < 60; $i++) {
                    $manager->persist(new Follow($this->getReference('user-'.$i), $main));
                }

                $manager->persist(new Follow($alice, $main));
                $manager->persist(new Follow($bob, $main));
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($main, $bob));
                $manager->persist(new Follow($this->getReference('user-0'), $main));

                for ($i = 0; $i < 15; $i++) {
                    $manager->persist(new Follow($alice, $this->getReference('user-'.$i)));
                }

                $manager->flush();
            }
        }, false);

        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/'.$mainId.'/followers?limit=5');
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(5, $items);
        $I->assertEquals(5, $lastValue);

        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertEquals('alice_user_name alice_user_surname', $items[0]['displayName']);
        $I->assertEquals('bob_user_name bob_user_surname', $items[1]['displayName']);
        $I->assertEquals('name-0 surname-0', $items[2]['displayName']);

        $I->seeResponseContainsJson([
            'response' => [
                ['displayName' => 'alice_user_name alice_user_surname', 'isFollowing' => true],
                ['displayName' => 'bob_user_name bob_user_surname', 'isFollowing' => true],
                ['displayName' => 'name-0 surname-0', 'isFollowing' => false],
            ]
        ]);

        $I->sendGet('/v1/follow/'.$mainId.'/followers?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(5, $items);
        $I->assertEquals(10, $lastValue);

        $I->sendGet('/v1/follow/'.$mainId.'/followers?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(3, $items);
        $I->assertEquals(null, $lastValue);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/'.$aliceId.'/following?limit=5');
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(5, $items);
        $I->assertEquals(5, $lastValue);

        $I->sendGet('/v1/follow/'.$aliceId.'/following?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(5, $items);
        $I->assertEquals(10, $lastValue);

        $I->sendGet('/v1/follow/'.$aliceId.'/following?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(5, $items);
        $I->assertEquals(15, $lastValue);

        $I->sendGet('/v1/follow/'.$aliceId.'/following?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(1, $items);
        $I->assertEquals(null, $lastValue);
    }

    public function testFollowing(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class, VideoRoomFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mike->state = User::STATE_BANNED;

                $bob->lastTimeActivity = time() + 360;
                $main->lastTimeActivity = time() - 360;

                $manager->persist(new Follow($main, $bob));
                $manager->persist(new Follow($main, $alice));

                $manager->persist(new Follow($mike, $alice));

                $manager->persist(new Follow($alice, $main));
                $manager->persist(new Follow($alice, $bob));

                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);
        $bobId = $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);

        $I->sendGet("/v1/follow/$aliceId/following");
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [
            self::BOB_USER_NAME,
            self::MAIN_USER_NAME,
        ]);

        $I->sendGet("/v1/follow/$aliceId/following?exceptMutual=1");
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [
            self::BOB_USER_NAME,
        ]);

        // filter with search
        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(2, 'bo', [$bobId])
        );

        $I->sendGet("/v1/follow/{$aliceId}/following", ['search' => 'bo']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [self::BOB_USER_NAME]);

        $I->sendGet("/v1/follow/{$aliceId}/following?exceptMutual=1", ['search' => 'bo']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [self::BOB_USER_NAME]);

        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(1, 'ali', [$aliceId])
        );
        $I->sendGet("/v1/follow/{$aliceId}/following?exceptMutual=1", ['search' => 'ali']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, []);
    }

    public function testFriends(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class, VideoRoomFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $club = new Club($main, 'Main club 01a255df-87a0-4aab-b0c7-9de89f7d5871');
                $club->id = Uuid::fromString('01a255df-87a0-4aab-b0c7-9de89f7d5871');
                $manager->persist($club);

                $mike->state = User::STATE_BANNED;

                $bob->lastTimeActivity = time() + 360;
                $main->lastTimeActivity = time() - 360;
                $alice->onlineInVideoRoom = true;

                $manager->persist(new Follow($main, $bob));
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($main, $mike));

                $manager->persist(new Follow($mike, $main));

                $manager->persist(new Follow($alice, $main));
                $manager->persist(new Follow($alice, $bob));

                $manager->persist(new ClubParticipant($club, $bob, $main));

                $follow5 = new Follow($bob, $alice);
                $follow5->createdAt = time() + 10;
                $manager->persist($follow5);

                $manager->flush();
            }
        }, false);

        //Main 1 friends
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/friends');
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [
            'alice_user_name',
        ]);

        $I->sendGet('/v1/follow/friends?forInviteClub=01a255df-87a0-4aab-b0c7-9de89f7d5871');
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [
            'alice_user_name',
        ]);

        //Alice 2 friends
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/friends');
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [
            'bob_user_name',
            'main_user_name'
        ]);

        //Alice 0 friends without clubs
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/friends?forInviteClub=01a255df-87a0-4aab-b0c7-9de89f7d5871');
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [
        ]);

        //Bob 1 friend - Alice
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/friends');
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [
            'alice_user_name',
        ]);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $videoRoom = $manager->getRepository('App:VideoChat\VideoRoom')
                    ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $meeting = new VideoMeeting($videoRoom, uniqid(), time());
                $manager->persist($meeting);
                $manager->persist(new VideoMeetingParticipant($meeting, $alice, time()));

                $manager->flush();
            }
        }, true);

        //Bob 1 friend - Alice
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/friends?forPingInVideoRoom='.self::VIDEO_ROOM_TEST_NAME);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, []);
    }

    public function subscribeUserWaitingList(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mike->state = User::STATE_WAITING_LIST;
                $bob->state = User::STATE_INVITED;
                $alice->state = User::STATE_NOT_INVITED;

                $manager->persist($mike);
                $manager->persist($bob);
                $manager->persist($alice);

                $manager->flush();
            }
        }, true);

        $mikeId = $I->grabFromRepository(User::class, 'id', ['email' => self::MIKE_USER_EMAIL]);
        $bobId = $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/follow/subscribe', json_encode([$aliceId]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPost('/v1/follow/subscribe', json_encode([$bobId]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPost('/v1/follow/subscribe', json_encode([$mikeId]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInRepository(Follow::class, [
            'follower' => ['email' => BaseCest::MAIN_ACCESS_TOKEN],
            'user' => ['email' => BaseCest::MIKE_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(Follow::class, [
            'follower' => ['email' => BaseCest::MAIN_ACCESS_TOKEN],
            'user' => ['email' => BaseCest::ALICE_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(Follow::class, [
            'follower' => ['email' => BaseCest::MAIN_ACCESS_TOKEN],
            'user' => ['email' => BaseCest::BOB_USER_EMAIL],
        ]);
    }

    public function testFollowersFilters(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager): void
            {
                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $manager->persist(new Follow($alice, $main));
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($bob, $main));

                $manager->flush();
            }
        });

        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet("/v1/follow/{$mainId}/followers", ['pendingOnly' => 1]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [self::BOB_USER_NAME]);

        $I->sendGet("/v1/follow/{$mainId}/followers", ['mutualOnly' => 1]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [self::ALICE_USER_NAME]);

        // filter with search
        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(3, 'ali', [$aliceId])
        );

        $I->sendGet("/v1/follow/{$mainId}/followers", ['search' => 'ali']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [self::ALICE_USER_NAME]);

        $I->sendGet("/v1/follow/{$mainId}/followers", [
            'mutualOnly' => 1,
            'search' => 'ali',
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, [self::ALICE_USER_NAME]);

        $I->sendGet("/v1/follow/{$mainId}/followers", [
            'pendingOnly' => 1,
            'search' => 'ali',
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertItemNames($I, []);
    }

    public function followedByCest(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $john = new User();
                $john->name = 'John';
                $john->surname = 'John';
                $manager->persist($john);
                $manager->flush();

                $sara = new User();
                $sara->name = 'Sara';
                $sara->surname = 'Sara';
                $sara->state = User::STATE_VERIFIED;
                $manager->persist($sara);
                $manager->flush();

                $margo = new User();
                $margo->name = 'Margo';
                $margo->surname = 'Margo';
                $margo->state = User::STATE_VERIFIED;
                $manager->persist($margo);
                $manager->flush();

                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($main, $bob));
                $manager->persist(new Follow($main, $john));
                $manager->persist(new Follow($main, $sara));
                $manager->persist(new Follow($main, $margo));

                $manager->persist(new Follow($alice, $mike));
                $manager->persist(new Follow($bob, $mike));
                $manager->persist(new Follow($sara, $mike));
                $manager->persist(new Follow($margo, $mike));

                $manager->flush();
            }
        }, true);

        $mikeId = $I->grabFromRepository(User::class, 'id', ['email' => self::MIKE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/'.$mikeId.'/followed-by/short');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'users' => [
                    [
                        'name' => 'Sara',
                        'surname' => 'Sara',
                        'displayName' => 'Sara Sara',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => false,
                    ],
                    [
                        'name' => 'Margo',
                        'surname' => 'Margo',
                        'displayName' => 'Margo Margo',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => false,
                    ],
                    [
                        'name' => 'bob_user_name',
                        'surname' => 'bob_user_surname',
                        'displayName' => 'bob_user_name bob_user_surname',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => false,
                    ],
                ],
                'totalCount' => 4,
            ]
        ]);

        $I->sendGet('/v1/follow/'.$mikeId.'/followed-by?limit=2');
        $I->seeResponseCodeIs(HttpCode::OK);
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertCount(2, $items);
        $I->assertNotNull($lastValue);
        $I->assertEquals('Margo Margo', $items[0]['displayName']);
        $I->assertEquals('Sara Sara', $items[1]['displayName']);
        $I->seeResponseMatchesJsonTypeStrict([
            'items' => [
                UserCest::USER_MIDDLE_RESPONSE_JSON,
                UserCest::USER_MIDDLE_RESPONSE_JSON
            ],
            'lastValue' => 'integer|null',
        ]);

        $I->sendGet('/v1/follow/'.$mikeId.'/followed-by?limit=2&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertCount(2, $items);
        $I->assertNull($lastValue);
        $I->assertEquals('bob_user_name bob_user_surname', $items[0]['displayName']);
        $I->assertEquals('alice_user_name alice_user_surname', $items[1]['displayName']);
        $I->seeResponseMatchesJsonTypeStrict([
            'items' => [
                UserCest::USER_MIDDLE_RESPONSE_JSON,
                UserCest::USER_MIDDLE_RESPONSE_JSON
            ],
            'lastValue' => 'integer|null',
        ]);
    }

    public function testConnectedCount(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            use FriendshipFixtureTrait;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mike->state = User::STATE_BANNED;

                $this->makeFriends($alice, $main);
                $this->makeFriends($bob, $main);
                $this->makeFriends($alice, $mike);

                $manager->flush();
            }
        }, true);

        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet("/v1/follow/{$alice->id}/counters");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'connectedCount' => 1,
            ],
        ]);
    }

    public function testConnectingCount(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            use FriendshipFixtureTrait;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mike->state = User::STATE_BANNED;

                $manager->persist(new Follow($alice, $main));

                $manager->persist(new Follow($alice, $mike));

                $this->makeFriends($alice, $bob);

                $manager->flush();
            }
        }, true);

        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet("/v1/follow/{$alice->id}/counters");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'connectingCount' => 1,
            ],
        ]);
    }

    public function testMutualFriendsCount(ApiTester $I): void
    {
        $I->loadFixtures(new class extends Fixture {
            use FriendshipFixtureTrait;
            use UserFixtureTrait;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $this->createMutualFriend($main, $alice, $bob);

                $mike->state = User::STATE_BANNED;
                $this->createMutualFriend($main, $alice, $mike);

                $this->makeFriends($alice, $this->createUser('jim'));
                $this->makeFriends($main, $this->createUser('katy'));

                $manager->flush();
            }

            private function createMutualFriend(User $user1, User $user2, User $friend): void
            {
                $this->makeFriends($user1, $friend);
                $this->makeFriends($user2, $friend);
            }
        }, true);

        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet("/v1/follow/{$alice->id}/counters");
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'mutualFriendsCount' => 1,
            ],
        ]);
    }

    public function testMutualFriends(ApiTester $I): void
    {
        $I->markTestSkipped();

        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->loadFixtures(new class extends Fixture {
            use FriendshipFixtureTrait;
            use UserFixtureTrait;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                for ($i = 0; $i < 5; $i++) {
                    $this->createMutualFriend($alice, $main, $this->createUser("Mutual friend {$i}"));
                }

                $this->createMutualFriend($this->createUser('User 1'), $this->createUser('User 2'), $alice);
                $this->createMutualFriend($this->createUser('User 3'), $this->createUser('User 4'), $main);

                $mike->state = User::STATE_BANNED;
                $this->createMutualFriend($main, $alice, $mike);

                $this->makeFriends($alice, $this->createUser('Jim'));
                $this->makeFriends($main, $this->createUser('Katy'));

                $manager->persist(new Follow($this->createUser('Alice follower'), $alice));
                $manager->persist(new Follow($alice, $this->createUser('Alice followee')));

                $manager->persist(new Follow($this->createUser('Main follower'), $main));
                $manager->persist(new Follow($main, $this->createUser('Main followee')));

                $manager->flush();
            }

            private function createMutualFriend(User $user1, User $user2, User $friend): void
            {
                $this->makeFriends($user1, $friend);
                $this->makeFriends($user2, $friend);
            }
        });

        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet("/v1/follow/{$alice->id}/mutual-friends", [
            'limit' => 2,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $this->assertLastValue($I, 2);
        $this->assertTotalCount($I, 5);
        $this->assertItemNames($I, [
            'Mutual friend 0',
            'Mutual friend 1',
        ]);

        $I->sendGet("/v1/follow/{$alice->id}/mutual-friends", [
            'limit' => 2,
            'lastValue' => $lastValue,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $this->assertLastValue($I, 4);
        $this->assertTotalCount($I, 5);
        $this->assertItemNames($I, [
            'Mutual friend 2',
            'Mutual friend 3',
        ]);

        $I->sendGet("/v1/follow/{$alice->id}/mutual-friends", [
            'limit' => 2,
            'lastValue' => $lastValue,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->assertLastValue($I, null);
        $this->assertTotalCount($I, 5);
        $this->assertItemNames($I, [
            'Mutual friend 4',
        ]);
    }

    private function assertLastValue(ApiTester $I, ?int $expectedValue): ?int
    {
        $actualValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];

        $I->assertEquals($expectedValue, $actualValue);

        return $actualValue;
    }

    private function assertTotalCount(ApiTester $I, ?int $expectedCount): void
    {
        $actualCount = $I->grabDataFromResponseByJsonPath('$.response.totalCount')[0];

        $I->assertEquals($expectedCount, $actualCount);
    }

    private function assertItemNames(ApiTester $I, array $expectedNames): void
    {
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertNotNull($items);
        $I->assertEquals($expectedNames, array_column($items, 'name'));
    }
}
