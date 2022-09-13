<?php

namespace App\Tests\Event;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\DataFixtures\AccessTokenFixture;
use App\DataFixtures\VideoRoomFixture;
use App\DTO\V1\Event\EventScheduleResponse;
use App\Entity\Activity\RegisteredAsCoHostActivity;
use App\Entity\Activity\RegisteredAsSpeakerActivity;
use App\Entity\Community\Community;
use App\Entity\Ethereum\Token;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\Follow\Follow;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\User;
use App\Entity\User\Device;
use App\Entity\VideoChat\VideoRoom;
use App\Message\SendNotificationMessage;
use App\Message\SendNotificationMessageBatch;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class EventScheduleCest extends BaseCest
{
    public function testAcceptance(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class, VideoRoomFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                foreach ([$main, $alice, $bob, $mike] as $userA) {
                    foreach ([$main, $alice, $bob, $mike] as $userB) {
                        if ($userA->id != $userB->id) {
                            $manager->persist(new Follow($userA, $userB));
                        }
                    }
                }

                $manager->persist(new Device(
                    Uuid::uuid4(),
                    $alice,
                    Device::TYPE_IOS_REACT,
                    'token',
                    null,
                    'RU'
                ));

                $manager->persist(new Device(
                    Uuid::uuid4(),
                    $mike,
                    Device::TYPE_IOS_REACT,
                    'token_mike',
                    null,
                    'RU'
                ));

                $interestGroup = new InterestGroup('InterestGroupForTesting');
                $manager->persist(new Interest($interestGroup, 'InterestTestA', 0, false));
                $manager->persist(new Interest($interestGroup, 'InterestTestB', 0, false));
                $manager->persist(new Interest($interestGroup, 'InterestTestC', 0, false));
                $manager->persist(new Interest($interestGroup, 'InterestTestD', 0, false));
                $manager->persist($interestGroup);

                $languageInterestRussia = new User\Language('Russia', 'RU');
                $languageInterestRussia->automaticChooseForRegionCodes = ['RU', 'UA', 'BY'];
                $manager->persist($languageInterestRussia);

                $languageInterestEnglish = new User\Language('English', 'EN');
                $languageInterestEnglish->isDefaultInterestForRegions = true;
                $manager->persist($languageInterestEnglish);

                $alice->addNativeLanguage($languageInterestRussia);
                $manager->persist($alice);

                $manager->flush();

                $token = new Token();
                $token->id = Uuid::fromString('233d73e6-8432-4c4a-8b5e-9ef6af2d3e86');
                $token->minAmount = 1;
                $token->landingUrl = 'land';
                $token->tokenId = '2';
                $token->network = 'goerli';
                $token->isInternal = true;
                $token->contractType = 'erc-1155';
                $token->contractAddress = '0x9842163CC45fC2a37075f475DCd07244dadd25FC';

                $manager->persist($token);
                $manager->flush();
            }
        }, false);

        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $bobId = $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);
        $mikeId = $I->grabFromRepository(User::class, 'id', ['email' => self::MIKE_USER_EMAIL]);

        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessageBatch &&
                $message->getBatch()[0]->platformType == Device::TYPE_IOS_REACT &&
                //phpcs:ignore
                $message->getBatch()[0]->message == 'main_user_name m. is going to talk about “My new event”. It starts on '.date('l, F d \a\t h:i A', time() + 10800 + 3600 * 5).'. Tap to take a look 👉' &&
                $message->getBatch()[0]->options['type'] == 'event-schedule' &&
                !empty($message->getBatch()[0]->options['eventScheduleId']);
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessageBatch::class)))->once();

        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                //phpcs:ignore
                $message->message == 'Don’t miss out! You’ve been appointed as a moderator by main_user_name m. for “My new event” which starts on '.date('l, F d \a\t h:i A', time() + 10800 + 3600 * 5) &&
                $message->options['type'] == 'event-schedule' &&
                !empty($message->options['eventScheduleId']);
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();

        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                //phpcs:ignore
                $message->message == 'Don’t miss out! You’ve been appointed as a speaker by main_user_name m. for “My new event” which starts on '.date('l, F d \a\t h:i A', time() + 10800 + 3600 * 2) &&
                $message->options['type'] == 'event-schedule' &&
                !empty($message->options['eventScheduleId']);
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();

        $I->mockService(MessageBusInterface::class, $busMock);

        $interestA = $I->grabEntityFromRepository(Interest::class, ['name' => 'InterestTestA'])->id;
        $interestB = $I->grabEntityFromRepository(Interest::class, ['name' => 'InterestTestB'])->id;
        $interestC = $I->grabEntityFromRepository(Interest::class, ['name' => 'InterestTestC'])->id;
        $interestD = $I->grabEntityFromRepository(Interest::class, ['name' => 'InterestTestD'])->id;

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v1/language');
        $I->seeResponseCodeIs(HttpCode::OK);
        $ru = $I->grabDataFromResponseByJsonPath('$.response[0]')[0];
        $en = $I->grabDataFromResponseByJsonPath('$.response[1]')[0];
        $I->assertEquals('English', $en['name']);
        $I->assertEquals('Russia', $ru['name']);

        $I->sendPost('/v1/event-schedule', json_encode([
            'title' => 'My new event',
            'date' => time() + 3600 * 5,
            'description' => 'My new event with my dear friends',
            'participants' => [
                ['id' => (string) $mainId],
                ['id' => (string) $bobId],
                ['id' => (string) $aliceId]
            ],
            'specialGuests' => [
                ['id' => (string) $mikeId],
            ],
            'interests' => [
                ['id' => $interestA],
                ['id' => $interestB],
            ],
            'language' => $ru['id'],
            'tokens' => ['233d73e6-8432-4c4a-8b5e-9ef6af2d3e86'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        /** @var EventSchedule $entitySchedule */
        $entitySchedule = $I->grabEntityFromRepository(EventSchedule::class, [
            'name' => 'My new event',
            'owner' => [
                'email' => self::MAIN_USER_EMAIL
            ],
            'description' => 'My new event with my dear friends'
        ]);

        $I->assertContains('RU', $entitySchedule->languages);
        $I->assertCount(1, $entitySchedule->languages);
        $I->assertCount(2, $entitySchedule->interests->toArray());
        $I->assertFalse(
            $entitySchedule->interests->filter(
                fn(EventScheduleInterest $i) => $i->interest->id == $interestA
            )->isEmpty()
        );
        $I->assertFalse(
            $entitySchedule->interests->filter(
                fn(EventScheduleInterest $i) => $i->interest->id == $interestB
            )->isEmpty()
        );

        $I->seeInRepository(RegisteredAsSpeakerActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::MIKE_USER_EMAIL],
        ]);
        $I->seeInRepository(RegisteredAsCoHostActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);
        $I->seeInRepository(RegisteredAsCoHostActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::BOB_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(RegisteredAsCoHostActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::MAIN_USER_EMAIL],
        ]);

        $I->seeInRepository(EventScheduleParticipant::class, [
            'user' => ['email' => self::ALICE_USER_EMAIL],
            'event' => ['name' => 'My new event']
        ]);
        $I->seeInRepository(EventScheduleParticipant::class, [
            'user' => ['email' => self::BOB_USER_EMAIL],
            'event' => ['name' => 'My new event']
        ]);
        $I->seeInRepository(EventScheduleParticipant::class, [
            'user' => ['email' => self::MAIN_USER_EMAIL],
            'event' => ['name' => 'My new event']
        ]);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                $message->pushToken == 'token' &&
                //phpcs:ignore
                $message->message == 'Don’t miss out! You’ve been appointed as a speaker by main_user_name m. for “My new event updated (updated)” which starts on '.date('l, F d \a\t h:i A', time() + 10800 + 3600 * 10) &&
                $message->options['type'] == 'event-schedule' &&
                !empty($message->options['eventScheduleId']);
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                $message->pushToken == 'token_mike' &&
                //phpcs:ignore
                $message->message == 'Don’t miss out! You’ve been appointed as a moderator by main_user_name m. for “My new event updated (updated)” which starts on '.date('l, F d \a\t h:i A', time() + 10800 + 3600 * 7) &&
                $message->options['type'] == 'event-schedule' &&
                !empty($message->options['eventScheduleId']);
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();
        $I->mockService(MessageBusInterface::class, $busMock);

        $dateTime = time() + 3600 * 10;
        $I->sendPatch('/v1/event-schedule/'.$entitySchedule->id, json_encode([
            'title' => 'My new event updated (updated)',
            'date' => $dateTime,
            'description' => 'My new event with my dear friends (updated)',
            'language' =>  $en['id'],
            'participants' => [
                ['id' => (string) $mikeId],
            ],
            'specialGuests' => [
                ['id' => (string) $aliceId]
            ],
            'interests' => [
                ['id' => $interestC],
                ['id' => $interestD],
                ['id' => $en['id']],
            ]
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'id' => $entitySchedule->id->toString(),
                'title' => 'My new event updated (updated)',
                'description' => 'My new event with my dear friends (updated)',
                'participants' => [
                    [
                        'isOwner' => true,
                        'name' => 'main_user_name',
                        'surname' => 'main_user_surname',
                        'displayName' => 'main_user_name main_user_surname',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => true,
                    ],
                    [
                        'isOwner' => false,
                        'name' => 'Mike',
                        'surname' => 'Mike',
                        'displayName' => 'Mike Mike',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => false,
                    ],
                    [
                        'isOwner' => false,
                        'name' => 'alice_user_name',
                        'surname' => 'alice_user_surname',
                        'displayName' => 'alice_user_name alice_user_surname',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => false,
                    ],
                ],
                'isAlreadySubscribedToAllParticipants' => true,
                'isOwned' => true,
                'state' => 'create_later',
                'roomId' => null,
                'roomPass' => null,
                'interests' => [
                    [
                        'name' => 'InterestTestC',
                    ],
                    [
                        'name' => 'InterestTestD',
                    ],
                ],
                'language' => [
                    'id' => $en['id'],
                    'name' => $en['name'],
                ],
            ],
        ]);

        /** @var EventSchedule $entitySchedule */
        $entitySchedule = $I->grabEntityFromRepository(EventSchedule::class, ['id' => $entitySchedule->id]);
        $I->assertNotContains('RU', $entitySchedule->languages);
        $I->assertContains('EN', $entitySchedule->languages);

        $I->assertCount(2, $entitySchedule->interests->toArray());
        $I->assertTrue($entitySchedule->interests->filter(
            fn(EventScheduleInterest $i) => $i->interest->id == $interestA
        )->isEmpty());
        $I->assertTrue($entitySchedule->interests->filter(
            fn(EventScheduleInterest $i) => $i->interest->id == $interestB
        )->isEmpty());
        $I->assertFalse($entitySchedule->interests->filter(
            fn(EventScheduleInterest $i) => $i->interest->id == $interestC
        )->isEmpty());
        $I->assertFalse($entitySchedule->interests->filter(
            fn(EventScheduleInterest $i) => $i->interest->id == $interestD
        )->isEmpty());

        //Check remove activities from removed participants
        $I->dontSeeInRepository(RegisteredAsCoHostActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(RegisteredAsCoHostActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::BOB_USER_EMAIL],
        ]);
        $I->dontSeeInRepository(RegisteredAsCoHostActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::MAIN_USER_EMAIL],
        ]);

        //Check remove participants
        $I->dontSeeInRepository(EventScheduleParticipant::class, [
            'user' => ['email' => self::BOB_USER_EMAIL],
            'event' => ['id' => $entitySchedule->id]
        ]);
        //Owner saved
        $I->seeInRepository(EventScheduleParticipant::class, [
            'user' => ['email' => self::MAIN_USER_EMAIL],
            'event' => ['id' => $entitySchedule->id]
        ]);

        //Check new participant activity \ participant rows exists
        $I->seeInRepository(RegisteredAsSpeakerActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::ALICE_USER_EMAIL],
        ]);
        $I->seeInRepository(EventScheduleParticipant::class, [
            'user' => ['email' => self::ALICE_USER_EMAIL],
            'event' => ['id' => $entitySchedule->id]
        ]);

        $I->seeInRepository(RegisteredAsCoHostActivity::class, [
            'eventSchedule' => ['id' => $entitySchedule->id],
            'user' => ['email' => self::MIKE_USER_EMAIL],
        ]);
        $I->seeInRepository(EventScheduleParticipant::class, [
            'user' => ['email' => self::MIKE_USER_EMAIL],
            'event' => ['id' => $entitySchedule->id]
        ]);

        /** @var EventSchedule $eventScheduleUpdated */
        $eventScheduleUpdated = $I->grabEntityFromRepository(EventSchedule::class, ['id' => $entitySchedule->id]);
        $I->assertEquals($dateTime, $eventScheduleUpdated->dateTime);
        $I->assertEquals('My new event with my dear friends (updated)', $eventScheduleUpdated->description);
        $I->assertEquals('My new event updated (updated)', $eventScheduleUpdated->name);

        $createdEventId = $I->grabDataFromResponseByJsonPath('$.response.id')[0];
        /** @var EventSchedule $eventSchedule */
        $eventSchedule = $I->grabEntityFromRepository(EventSchedule::class, ['id' => $createdEventId]);
        $I->assertNotNull($eventSchedule);

        $I->sendPatch('/v1/event-schedule/'.$entitySchedule->id, json_encode([
            'title' => 'My new event updated (updated)',
            'date' => time() - 1,
            'description' => 'My new event with my dear friends (updated)',
            'language' =>  $en['id'],
            'participants' => [
                ['id' => (string) $mikeId],
            ],
            'specialGuests' => [
                ['id' => (string) $aliceId]
            ],
            'interests' => [
                ['id' => $interestC],
                ['id' => $interestD],
                ['id' => $en['id']],
            ]
        ]));
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);

        $I->loadFixtures(new class($createdEventId) extends Fixture {
            private string $eventScheduleId;

            public function __construct(string $eventScheduleId)
            {
                $this->eventScheduleId = $eventScheduleId;
            }


            public function load(ObjectManager $manager)
            {
                $videoRoom = $manager->getRepository('App:VideoChat\VideoRoom')->findOneByName(
                    BaseCest::VIDEO_ROOM_BOB_NAME
                );

                $eventSchedule = $manager->getRepository('App:Event\EventSchedule')->find($this->eventScheduleId);
                $eventSchedule->videoRoom = $videoRoom;

                $videoRoom->eventSchedule = $eventSchedule;

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $manager->persist(new EventScheduleSubscription($eventSchedule, $main));

                $manager->persist($videoRoom);
                $manager->persist($eventSchedule);
                $manager->flush();
            }
        }, true);

        $I->sendGet('/v1/event-schedule/'.$entitySchedule->id);
        $I->seeResponseContainsJson([
            'response' => [
                'title' => 'My new event updated (updated)',
                'description' => 'My new event with my dear friends (updated)',
                'participants' => [
                    [
                        'isOwner' => true,
                        'name' => 'main_user_name',
                        'surname' => 'main_user_surname',
                        'displayName' => 'main_user_name main_user_surname',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => true,
                    ],
                    [
                        'isOwner' => false,
                        'name' => 'Mike',
                        'surname' => 'Mike',
                        'displayName' => 'Mike Mike',
                        'about' => '',
                        'username' => '',
                        'isDeleted' => false,
                        'online' => false,
                    ],
                ],
                'isAlreadySubscribedToAllParticipants' => true,
                'isOwned' => true,
                'state' => 'join',
                'roomId' => 'video_room_bob',
            ]
        ]);

        $I->sendDelete('/v1/event-schedule/'.$createdEventId);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInRepository(EventSchedule::class, ['id' => $createdEventId]);
        $I->seeInRepository(VideoRoom::class, ['community' => ['name' => BaseCest::VIDEO_ROOM_BOB_NAME]]);
    }

    public function testAddParticipantWithoutFriendshipWithOtherUsers(ApiTester $I)
    {
        ClockMock::withClockMock(1000);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($alice, $main));

                $manager->persist(new Follow($main, $bob));
                $manager->persist(new Follow($bob, $main));

                $manager->persist(new Follow($mike, $alice));
                $manager->persist(new Follow($alice, $mike));

                $eventSchedule = new EventSchedule($main, 'Test schedule', time(), '');
                $manager->persist($eventSchedule);

                $participant = new EventScheduleParticipant($eventSchedule, $alice);
                $manager->persist($participant);
                $participant = new EventScheduleParticipant($eventSchedule, $mike);
                $manager->persist($participant);

                $manager->flush();
            }

            public function getDependencies(): array
            {
                return [
                    AccessTokenFixture::class
                ];
            }
        }, false);

        $eventScheduleId = $I->grabFromRepository(EventSchedule::class, 'id', [
            'name' => 'Test schedule',
        ]);

        $bobId = $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);
        $mikeId = $I->grabFromRepository(User::class, 'id', ['email' => self::MIKE_USER_EMAIL]);
        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPatch("/v1/event-schedule/{$eventScheduleId}", json_encode([
            'title' => 'Test schedule',
            'date' => time(),
            'participants' => [
                ['id' => (string) $aliceId],
                ['id' => (string) $mainId],
                ['id' => (string) $mikeId],
                ['id' => (string) $bobId],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function eventScheduleItemTest(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $communityMain = new Community($main, uniqid('v1'), '');
                $communityMain->name = '605e53c12a14f';
                $communityMain->password = '605e53c26c69e';
                $manager->persist($communityMain);

                $eventSchedule = new EventSchedule($main, 'MainSchedule', time(), 'Ok');
                $eventSchedule->videoRoom = $communityMain->videoRoom;
                $communityMain->videoRoom->eventSchedule = $eventSchedule;
                $manager->persist($eventSchedule);
                $manager->persist($communityMain);

                $communityMain = new Community($main, uniqid('done'), '');
                $communityMain->videoRoom->doneAt = time();
                $manager->persist($communityMain);

                $eventScheduleDone = new EventSchedule($main, 'MainSchedule_Done', time(), 'Done');
                $eventScheduleDone->videoRoom = $communityMain->videoRoom;
                $communityMain->videoRoom->eventSchedule = $eventScheduleDone;
                $manager->persist($eventScheduleDone);

                $communityMain = new Community($main, uniqid('started'), '');
                $communityMain->videoRoom->doneAt = null;
                $communityMain->videoRoom->startedAt = time();
                $manager->persist($communityMain);

                $eventScheduleStarted = new EventSchedule($main, 'MainSchedule_Started', time(), 'Started');
                $eventScheduleStarted->videoRoom = $communityMain->videoRoom;
                $communityMain->videoRoom->eventSchedule = $eventScheduleStarted;
                $manager->persist($eventScheduleStarted);

                $eventScheduleExpired = new EventSchedule($main, 'MainSchedule_Expired', time() - 100, 'Done');
                $manager->persist($eventScheduleExpired);

                $eventScheduleFuture = new EventSchedule($main, 'MainSchedule_Future', time() + 510, 'Future');
                $manager->persist($eventScheduleFuture);

                $eventScheduleFuture = new EventSchedule($main, 'Future_120_sec', time() + 118, 'Future');
                $manager->persist($eventScheduleFuture);

                $manager->flush();
            }
        });

        $eventId = $I->grabFromRepository(EventSchedule::class, 'id', ['name' => 'MainSchedule']);
        $eventDoneId = $I->grabFromRepository(EventSchedule::class, 'id', ['name' => 'MainSchedule_Done']);
        $eventStartedId = $I->grabFromRepository(EventSchedule::class, 'id', ['name' => 'MainSchedule_Started']);
        $eventExpiredId = $I->grabFromRepository(EventSchedule::class, 'id', ['name' => 'MainSchedule_Expired']);
        $eventFutureId = $I->grabFromRepository(EventSchedule::class, 'id', ['name' => 'MainSchedule_Future']);
        $eventFuture120Sec = $I->grabFromRepository(EventSchedule::class, 'id', ['name' => 'Future_120_sec']);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v1/event-schedule/'.$eventId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_JOIN,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );
        $I->assertEquals('605e53c12a14f', $I->grabDataFromResponseByJsonPath('$.response.roomId')[0]);
        $I->assertEquals('605e53c26c69e', $I->grabDataFromResponseByJsonPath('$.response.roomPass')[0]);

        $I->sendGet('/v1/event-schedule/'.$eventDoneId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_EXPIRED,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->sendGet('/v1/event-schedule/'.$eventStartedId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_JOIN,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->sendGet('/v1/event-schedule/'.$eventExpiredId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_CREATE_VIDEO_ROOM,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->sendGet('/v1/event-schedule/'.$eventFutureId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_CREATE_LATER,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);

        $I->sendGet('/v1/event-schedule/'.$eventId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_CHECK_LATER,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->sendGet('/v1/event-schedule/'.$eventDoneId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_EXPIRED,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->sendGet('/v1/event-schedule/'.$eventStartedId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_JOIN,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->sendGet('/v1/event-schedule/'.$eventExpiredId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_CHECK_LATER,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->sendGet('/v1/event-schedule/'.$eventFutureId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_CHECK_LATER,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/event-schedule/'.$eventFuture120Sec);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals(
            EventScheduleResponse::STATE_CREATE_VIDEO_ROOM,
            $I->grabDataFromResponseByJsonPath('$.response.state')[0]
        );
    }
}
