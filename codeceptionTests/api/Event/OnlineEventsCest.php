<?php

namespace App\Tests\Event;

use App\DataFixtures\AccessTokenFixture;
use App\Entity\Community\Community;
use App\Entity\Event\EventSchedule;
use App\Entity\Follow\Follow;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Message\SendNotificationMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OnlineEventsCest extends BaseCest
{
    use EventInterestTrait;

    public function paginationCest(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $startTime = 1648807260;

                $repository = $manager->getRepository('App:User');

                $main = $repository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $repository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($alice, $main));

                for ($i = 0; $i < 10; $i++) {
                    $community = new Community($main, 'alice-'.$i, 'Alice-'.$i);
                    $community->videoRoom->startedAt = $startTime + $i;
                    $community->videoRoom->createdAt = $startTime + $i;
                    $community->videoRoom->addInvitedUser($alice);
                    $community->videoRoom->type = VideoRoom::TYPE_NEW;
                    $manager->persist($community);

                    $meeting = new VideoMeeting($community->videoRoom, 'alice-meeting-'.$i, time(), 'jitsi');
                    $manager->persist($meeting);
                    $manager->flush();
                }

                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/event/online?limit=5');

        $I->assertCount(5, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->assertEquals('Alice-9', $I->grabDataFromResponseByJsonPath('$.response.items[0].title')[0]);
        $I->assertEquals('Alice-8', $I->grabDataFromResponseByJsonPath('$.response.items[1].title')[0]);
        $I->assertEquals('Alice-7', $I->grabDataFromResponseByJsonPath('$.response.items[2].title')[0]);
        $I->assertEquals('Alice-6', $I->grabDataFromResponseByJsonPath('$.response.items[3].title')[0]);
        $I->assertEquals('Alice-5', $I->grabDataFromResponseByJsonPath('$.response.items[4].title')[0]);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNotNull($lastValue);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $startTime = 1648807260;

                $repository = $manager->getRepository('App:User');

                $main = $repository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $repository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $community = new Community($main, 'alice-10', 'Alice-10');
                $community->videoRoom->startedAt = $startTime + 10;
                $community->videoRoom->createdAt = $startTime + 10;
                $community->videoRoom->addInvitedUser($alice);
                $community->videoRoom->type = VideoRoom::TYPE_NEW;
                $manager->persist($community);

                $meeting = new VideoMeeting($community->videoRoom, 'alice-meeting-10', time(), 'jitsi');
                $manager->persist($meeting);

                $manager->flush();
            }
        }, true);

        $I->sendGet('/v1/event/online?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
//        $I->assertCount(5, $I->grabDataFromResponse('items'));
        $I->assertEquals('Alice-5', $I->grabDataFromResponse('items[0].title'));
        $I->assertEquals('Alice-4', $I->grabDataFromResponse('items[1].title'));
        $I->assertEquals('Alice-3', $I->grabDataFromResponse('items[2].title'));
        $I->assertEquals('Alice-2', $I->grabDataFromResponse('items[3].title'));
        $I->assertEquals('Alice-1', $I->grabDataFromResponse('items[4].title'));
        $lastValue = $I->grabDataFromResponse('lastValue');
//        $I->assertNull($lastValue);

        $I->sendGet('/v1/event/online?limit=5');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(5, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->assertEquals('Alice-10', $I->grabDataFromResponseByJsonPath('$.response.items[0].title')[0]);
        $I->assertEquals('Alice-9', $I->grabDataFromResponseByJsonPath('$.response.items[1].title')[0]);
        $I->assertEquals('Alice-8', $I->grabDataFromResponseByJsonPath('$.response.items[2].title')[0]);
        $I->assertEquals('Alice-7', $I->grabDataFromResponseByJsonPath('$.response.items[3].title')[0]);
        $I->assertEquals('Alice-6', $I->grabDataFromResponseByJsonPath('$.response.items[4].title')[0]);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNotNull($lastValue);

        $I->sendGet('/v1/event/online?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(5, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->assertEquals('Alice-5', $I->grabDataFromResponseByJsonPath('$.response.items[0].title')[0]);
        $I->assertEquals('Alice-4', $I->grabDataFromResponseByJsonPath('$.response.items[1].title')[0]);
        $I->assertEquals('Alice-3', $I->grabDataFromResponseByJsonPath('$.response.items[2].title')[0]);
        $I->assertEquals('Alice-2', $I->grabDataFromResponseByJsonPath('$.response.items[3].title')[0]);
        $I->assertEquals('Alice-1', $I->grabDataFromResponseByJsonPath('$.response.items[4].title')[0]);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNotNull($lastValue);

        $I->sendGet('/v1/event/online?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->assertEquals('Alice-0', $I->grabDataFromResponseByJsonPath('$.response.items[0].title')[0]);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNull($lastValue);
    }

    public function acceptanceTestOnlineEventsCest(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($main, $bob));

                $manager->persist(new Follow($mike, $alice));
                $manager->persist(new Follow($mike, $bob));
                $manager->persist(new Follow($mike, $main));

                $interestGroup = new InterestGroup('Group');
                $manager->persist($interestGroup);
                $interestA = new Interest($interestGroup, 'Interest A', 0, false);
                $manager->persist($interestA);
                $interestB = new Interest($interestGroup, 'Interest B', 0, false);
                $manager->persist($interestB);
                $interestC = new Interest($interestGroup, 'Interest C', 0, false);
                $interestC->globalSort = 1;
                $manager->persist($interestC);

                $languageInterestEnglish = new User\Language('English', 'EN');
                $manager->persist($languageInterestEnglish);
                $languageInterestRussia = new User\Language('Russia', 'RU');
                $manager->persist($languageInterestRussia);

                $main->addNativeLanguage($languageInterestRussia);
                $manager->persist($main);

                $alice->addNativeLanguage($languageInterestEnglish);
                $manager->persist($alice);

                $aliceEventSchedule = new EventSchedule($alice, 'Community Alice', time(), '');
                $aliceEventSchedule->addInterest($interestA);
                $aliceEventSchedule->addInterest($interestB);
                $aliceEventSchedule->addInterest($interestC);
                $aliceEventSchedule->language = $languageInterestEnglish;

                $bobEventSchedule = new EventSchedule($alice, 'Bob Alice', time(), '');
                $bobEventSchedule->addInterest($interestA);

                $aliceSubscription = new Subscription(
                    'Alice subscription',
                    500,
                    'stripe-id',
                    'stripe-price-id',
                    $alice
                );
                $aliceSubscription->isActive = true;
                $manager->persist($aliceSubscription);

                $bobSubscription = new Subscription(
                    'Bob subscription',
                    500,
                    'stripe-id-2',
                    'stripe-price-id-2',
                    $bob
                );
                $manager->persist($bobSubscription);

                $communityAlice = new Community($alice, '6051c4c478815', 'Community Alice');
                $communityAlice->password = '6051c4c478815';
                $communityAlice->videoRoom->eventSchedule = $aliceEventSchedule;
                $communityAlice->videoRoom->type = VideoRoom::TYPE_NEW;
                $communityAlice->videoRoom->startedAt = time();
                $communityAlice->videoRoom->subscription = $aliceSubscription;
                $meeting = new VideoMeeting($communityAlice->videoRoom, uniqid(), time());
                $communityAlice->videoRoom->config->withSpeakers = true;
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $alice, time(), null, true));
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $main, time(), null, true));
                $communityAlice->videoRoom->meetings->add($meeting);
                $communityAlice->createdAt = time();
                $manager->persist($communityAlice);
                $communityBob = new Community($bob, '6051c4c50c5ee', 'Community Bob');
                $communityBob->password = '6051c4c50c5ee';
                $communityBob->videoRoom->type = VideoRoom::TYPE_NEW;
                $communityBob->videoRoom->startedAt = time();
                $communityBob->createdAt = time() + 1;

                $meeting = new VideoMeeting($communityBob->videoRoom, uniqid(), time());
                $communityBob->videoRoom->config->withSpeakers = true;
                $communityBob->videoRoom->eventSchedule = $bobEventSchedule;
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $bob, time(), null, true));
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $alice, time()));
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $main, time()));
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $mike, time()));
                $communityBob->videoRoom->meetings->add($meeting);

                $manager->persist($communityBob);

                $communityMike = new Community($mike, '6051c4c56d1b2', 'Community Mike');
                $communityMike->videoRoom->type = VideoRoom::TYPE_NEW;
                $communityMike->videoRoom->startedAt = time();
                $communityMike->videoRoom->config->withSpeakers = true;
                $meeting = new VideoMeeting($communityMike->videoRoom, uniqid(), time());
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $mike, time(), null, true));
                $communityMike->videoRoom->meetings->add($meeting);
                $communityMike->videoRoom->eventSchedule = new EventSchedule($mike, 'Community Mike', time(), '');
                $communityMike->videoRoom->eventSchedule->language = $languageInterestEnglish;
                $manager->persist($communityMike);

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::MIKE_ACCESS_TOKEN);
        $I->sendGet('/v1/event/online');
        $I->seeResponseCodeIs(HttpCode::OK);

        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(3, $items);

        $I->assertEquals('Community Mike', $items[0]['title']);
        $I->assertEquals('Community Bob', $items[1]['title']);
        $I->assertEquals('Community Alice', $items[2]['title']);

        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'title' => 'Community Mike',
                        'participants' => [
                            [
                                'isSpeaker' => true,
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                            ],
                        ],
                        'online' => 0,
                        'speaking' => 1,
                        'roomId' => '6051c4c56d1b2',
                        'withSpeakers' => true,
                        'subscriptionId' => '',
                        'interests' => [],
                    ],
                    [
                        'title' => 'Community Alice',
                        'participants' => [
                            [
                                'isSpeaker' => true,
                                'avatar' => null,
                                'name' => 'alice_user_name',
                                'surname' => 'alice_user_surname',
                                'displayName' => 'alice_user_name alice_user_surname',
                                'isDeleted' => false,
                            ],
                            [
                                'isSpeaker' => true,
                                'avatar' => null,
                                'name' => 'main_user_name',
                                'surname' => 'main_user_surname',
                                'displayName' => 'main_user_name main_user_surname',
                                'isDeleted' => false,
                            ],
                        ],
                        'online' => 0,
                        'speaking' => 2,
                        'roomId' => '6051c4c478815',
                        'roomPass' => '6051c4c478815',
                        'withSpeakers' => true,
                        'interests' => [
                            ['name' => 'Interest A'],
                            ['name' => 'Interest B'],
                            ['name' => 'Interest C'],
                        ],
                    ],
                    [
                        'title' => 'Community Bob',
                        'participants' => [
                            [
                                'isSpeaker' => true,
                                'avatar' => null,
                                'name' => 'bob_user_name',
                                'surname' => 'bob_user_surname',
                                'displayName' => 'bob_user_name bob_user_surname',
                                'isDeleted' => false,
                            ],
                            [
                                'isSpeaker' => false,
                                'avatar' => null,
                                'name' => 'alice_user_name',
                                'surname' => 'alice_user_surname',
                                'displayName' => 'alice_user_name alice_user_surname',
                                'isDeleted' => false,
                            ],
                            [
                                'isSpeaker' => false,
                                'avatar' => null,
                                'name' => 'main_user_name',
                                'surname' => 'main_user_surname',
                                'displayName' => 'main_user_name main_user_surname',
                                'isDeleted' => false,
                            ],
                            [
                                'isSpeaker' => false,
                                'avatar' => null,
                                'name' => 'Mike',
                                'surname' => 'Mike',
                                'displayName' => 'Mike Mike',
                                'isDeleted' => false,
                            ],
                        ],
                        'online' => 3,
                        'speaking' => 1,
                        'roomId' => '6051c4c50c5ee',
                        'roomPass' => '6051c4c50c5ee',
                        'withSpeakers' => true,
                        'subscriptionId' => '',
                        'interests' => [
                            ['name' => 'Interest A'],
                        ],
                    ],
                ],
                'lastValue' => null,
            ],
        ]);

        $main = $I->grabEntityFromRepository(User::class, ['email' => BaseCest::MAIN_USER_EMAIL]);
//        dump($main->languages);

        //Check english language
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/event/online');
        $I->seeResponseCodeIs(HttpCode::OK);

        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(0, $items);
    }

    public function testInterestsWithoutEventSchedule(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $interestRepository = $manager->getRepository(Interest::class);
                $languageRepository = $manager->getRepository(User\Language::class);

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $manager->persist(new Follow($main, $alice));

                $english = $languageRepository->findOneBy(['code' => 'EN']);
                $russian = $languageRepository->findOneBy(['code' => 'RU']);

//                $alice->addInterest($english);
//                $alice->addInterest($russian);

                $alice->addNativeLanguage($english);

                $communityAlice = new Community($alice, '6051c4c478815', 'Community Alice');
                $communityAlice->password = '6051c4c478815';
                $communityAlice->videoRoom->type = VideoRoom::TYPE_NEW;
                $communityAlice->videoRoom->startedAt = time();
                $meeting = new VideoMeeting($communityAlice->videoRoom, uniqid(), time());
                $communityAlice->videoRoom->config->withSpeakers = true;
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $alice, time(), null, true));
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $main, time(), null, true));
                $communityAlice->videoRoom->meetings->add($meeting);
                $communityAlice->createdAt = time();
                $manager->persist($communityAlice);

                $manager->flush();
            }
        });

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v1/event/online');
        $I->seeResponseCodeIs(HttpCode::OK);

        $this->assertInterests($I, [
            'Community Alice' => [
                'Interest_1',
                'Interest_2',
            ],
        ]);
    }

    public function testUserWithDifferentLanguadeWasInvited(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $chinese = new User\Language('Chinese', 'CN');
                $manager->persist($chinese);

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $manager->persist(new User\Device(
                    Uuid::uuid4(),
                    $alice,
                    User\Device::TYPE_IOS_REACT,
                    'token',
                    null,
                    'RU'
                ));

                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($alice, $main));

                $videoRoom = $manager->getRepository('App:VideoChat\VideoRoom')
                    ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);
                $videoRoom->type = VideoRoom::TYPE_NEW;
                $videoRoom->language = $chinese;
                $videoRoom->startedAt = time();

                $meeting = new VideoMeeting($videoRoom, uniqid(), null);
                $manager->persist($meeting);

                $manager->persist(new VideoMeetingParticipant(
                    $meeting,
                    $main,
                    time(),
                    null,
                    true
                ));

                $manager->flush();
            }
        }, true);

        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        // ping user
        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)));
        $I->mockService(MessageBusInterface::class, $busMock);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users/'.$aliceId.'/'.self::VIDEO_ROOM_TEST_NAME.'/ping');
        $I->seeResponseCodeIs(HttpCode::OK);

        // get events list
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v1/event/online');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
    }
}
