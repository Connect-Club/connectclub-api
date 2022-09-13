<?php

namespace App\Tests\User;

use App\Entity\Community\Community;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Interest\Interest;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;

class SubscriptionCest extends BaseCest
{
//    public function testSubscriptions(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            private ObjectManager $manager;
//
//            public function load(ObjectManager $manager): void
//            {
//                $this->manager = $manager;
//
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//
//                $this->createSubscriptionsForAuthor($main, 3);
//                $this->createSubscriptionsForAuthor($alice, 1);
//
//                $manager->flush();
//            }
//
//            private function createSubscriptionsForAuthor(User $author, int $quantity): void
//            {
//                $uuids = [];
//                for ($i = 0; $i < $quantity; $i++) {
//                    $uuids[] = Uuid::uuid4();
//                }
//
//                usort($uuids, fn($uuid1, $uuid2) => strcmp((string) $uuid1, (string) $uuid2));
//
//                foreach ($uuids as $i => $uuid) {
//                    $subscription = new Subscription(
//                        "Subscription {$i} of {$author->email}",
//                        ($i % 3 + 1) * 500,
//                        $i,
//                        $i,
//                        $author
//                    );
//                    $subscription->description = "Description {$i} of {$author->email}";
//                    $subscription->id = $uuid;
//                    $this->manager->persist($subscription);
//                }
//            }
//        });
//
//        $mainId = $I->grabFromRepository(User::class, 'id', [
//            'email' => self::MAIN_USER_EMAIL,
//        ]);
//
//        $I->sendGet("/v1/user/$mainId/subscriptions");
//        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
//
//        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
//
//        $I->sendGet("/v1/user/$mainId/subscriptions");
//        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendGet("/v1/user/$mainId/subscriptions", [
//            'limit' => 2,
//        ]);
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $lastValue = $this->assertItems($I, [
//            [
//                'name' => 'Subscription 0 of ' . self::MAIN_USER_EMAIL,
//                'price' => 500,
//                'isActive' => false,
//                'description' => 'Description 0 of ' . self::MAIN_USER_EMAIL,
//            ],
//            [
//                'name' => 'Subscription 1 of ' . self::MAIN_USER_EMAIL,
//                'price' => 1000,
//                'isActive' => false,
//                'description' => 'Description 1 of ' . self::MAIN_USER_EMAIL,
//            ],
//        ]);
//        $this->assertItemsHasFields($I, ['id', 'createdAt']);
//
//        $I->sendGet("/v1/user/$mainId/subscriptions", [
//            'limit' => 2,
//            'lastValue' => $lastValue,
//        ]);
//        $lastValue = $this->assertItems($I, [
//            [
//                'name' => 'Subscription 2 of ' . self::MAIN_USER_EMAIL,
//                'price' => 1500,
//                'isActive' => false,
//                'description' => 'Description 2 of ' . self::MAIN_USER_EMAIL,
//            ]
//        ]);
//
//        $I->assertNull($lastValue);
//
//        $aliceId = $I->grabFromRepository(User::class, 'id', [
//            'email' => self::ALICE_USER_EMAIL,
//        ]);
//
//        $I->sendGet("/v1/user/$aliceId/subscriptions");
//        $lastValue = $this->assertItems($I, [
//            [
//                'name' => 'Subscription 0 of ' . self::ALICE_USER_EMAIL,
//                'price' => 500,
//                'isActive' => false,
//                'description' => 'Description 0 of ' . self::ALICE_USER_EMAIL,
//            ]
//        ]);
//
//        $I->assertNull($lastValue);
//    }
//
//    public function testEvents(ApiTester $I): void
//    {
//        ClockMock::withClockMock(7200);
//
//        $I->loadFixtures(new class extends Fixture {
//            private EntityManagerInterface $entityManager;
//            private int $createdEventCount = 0;
//
//            public function load(ObjectManager $manager)
//            {
//                $this->entityManager = $manager;
//
//                $this->createAliceEvents();
//                $this->createMainEvents();
//
//                $manager->flush();
//            }
//
//            private function createAliceEvents(): void
//            {
//                $manager = $this->entityManager;
//
//                $alice = $this->getUser(BaseCest::ALICE_USER_EMAIL);
//
//                $aliceSubscription = new Subscription(
//                    'Alice subscription',
//                    500,
//                    'stripe-id-2',
//                    'stripe-price-id-2',
//                    $alice
//                );
//                $manager->persist($aliceSubscription);
//
//                $eventSchedule = new EventSchedule(
//                    $alice,
//                    'Alice upcoming event',
//                    time() + 3600,
//                    'Description'
//                );
//                $eventSchedule->subscription = $aliceSubscription;
//                $manager->persist($eventSchedule);
//            }
//
//            private function createMainEvents(): void
//            {
//                $manager = $this->entityManager;
//
//                $main = $this->getUser(BaseCest::MAIN_USER_EMAIL);
//
//                $mainSubscription = new Subscription(
//                    'Main subscription',
//                    500,
//                    'stripe-id',
//                    'stripe-price-id',
//                    $main
//                );
//                $manager->persist($mainSubscription);
//
//                $manager->persist(new EventSchedule(
//                    $main,
//                    'Free upcoming event',
//                    time() + 3600,
//                    'Free description'
//                ));
//
//                $this->createUpcomingEvent($main, $mainSubscription);
//                $this->createUpcomingEvent($main, $mainSubscription);
//                $this->createUpcomingEvent($main, $mainSubscription);
//
//                $upcoming = $this->createUpcomingEvent($main, $mainSubscription);
//                $upcoming->addInterest($this->getInterest('📖 Writing'));
//                $upcoming->addInterest($this->getLanguage('RU'));
//                $upcoming->addInterest($this->getInterest('🔥 Burning Man'));
//                $upcoming->addInterest($this->getLanguage('EN'));
//
//                $this->createOnlineEvent($main, $mainSubscription);
//            }
//
//            private function createUpcomingEvent(
//                User $user,
//                Subscription $subscription
//            ): EventSchedule {
//                $eventSchedule = new EventSchedule(
//                    $user,
//                    "Upcoming event $this->createdEventCount",
//                    time() + 3600 + $this->createdEventCount,
//                    'Upcoming description',
//                    $this->getLanguage('RU')
//                );
//                $eventSchedule->endDateTime = time() + 7200 + $this->createdEventCount;
//                $eventSchedule->subscription = $subscription;
//                $this->entityManager->persist($eventSchedule);
//
//                $this->createdEventCount++;
//
//                return $eventSchedule;
//            }
//
//            private function createOnlineEvent(User $user, Subscription $subscription): void
//            {
//                $eventSchedule = new EventSchedule(
//                    $user,
//                    'Online event',
//                    time() - 3600,
//                    'Online description'
//                );
//                $eventSchedule->endDateTime = time() + 3600;
//                $eventSchedule->subscription = $subscription;
//                $this->entityManager->persist($eventSchedule);
//
//                $videoRoom = $this->createVideoRoom($user, 'Online room');
//                $videoRoom->eventSchedule = $eventSchedule;
//
//                $bob = $this->getUser(BaseCest::BOB_USER_EMAIL);
//                $alice = $this->getUser(BaseCest::ALICE_USER_EMAIL);
//                $mike = $this->getUser(BaseCest::MIKE_USER_EMAIL);
//
//                $this->entityManager->persist(new EventScheduleParticipant($eventSchedule, $bob));
//                $this->entityManager->persist(new EventScheduleParticipant($eventSchedule, $mike));
//
//                $this->createMeeting(
//                    $videoRoom,
//                    'first_meeting',
//                    time() - 3600,
//                    [
//                        $alice,
//                    ]
//                );
//                $this->createMeeting(
//                    $videoRoom,
//                    'second_meeting',
//                    time() - 1800,
//                    [
//                        $alice,
//                        $bob,
//                    ]
//                );
//            }
//
//            private function getUser(string $email): User
//            {
//                return $this->entityManager->getRepository(User::class)
//                    ->findOneBy(['email' => $email]);
//            }
//
//            private function createVideoRoom(User $user, string $name): VideoRoom
//            {
//                $community = new Community($user, $name, 'description');
//
//                $this->entityManager->persist($community);
//
//                return $community->videoRoom;
//            }
//
//            private function getInterest(string $name): Interest
//            {
//                return $this->entityManager->getRepository(Interest::class)
//                    ->findOneBy(['name' => $name]);
//            }
//
//            private function getLanguage(string $code): Interest
//            {
//                return $this->entityManager->getRepository(Interest::class)
//                    ->findOneBy(['languageCode' => $code]);
//            }
//
//            /**
//             * @param User[] $participants
//             */
//            private function createMeeting(
//                VideoRoom $videoRoom,
//                string $name,
//                int $startTime,
//                array $participants
//            ): void {
//                $videoMeeting = new VideoMeeting($videoRoom, $name, $startTime);
//
//                foreach ($participants as $i => $participant) {
//                    $this->entityManager->persist(new VideoMeetingParticipant(
//                        $videoMeeting,
//                        $participant,
//                        $startTime + 10 * $i
//                    ));
//                }
//
//                $this->entityManager->persist($videoMeeting);
//            }
//        });
//
//        $mainSubscriptionId = $I->grabFromRepository(Subscription::class, 'id', [
//            'name' => 'Main subscription',
//        ]);
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendGet("/v1/subscription/$mainSubscriptionId/events", [
//            'limit' => 2
//        ]);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $lastValue = $this->assertItems($I, [
//            [
//                'title' => 'Upcoming event 3',
//                'date' => time() + 3603,
//                'dateEnd' => time() + 7203,
//                'description' => 'Upcoming description',
//                'participants' => [],
//                'listenerCount' => 0,
//                'interests' => [
//                    [
//                        'name' => '🇷🇺 Russian',
//                        'isLanguage' => true,
//                    ],
//                    [
//                        'name' => '🇬🇧 English',
//                        'isLanguage' => true,
//                    ],
//                    [
//                        'name' => '📖 Writing',
//                    ],
//                    [
//                        'name' => '🔥 Burning Man',
//                    ],
//                ],
//                'language' => [
//                    'name' => '🇷🇺 Russian',
//                    'isLanguage' => true,
//                ],
//            ],
//        ]);
//
//        $this->assertItemNames($I, [
//            'Upcoming event 3',
//            'Upcoming event 2',
//        ]);
//
//        $I->assertNotNull($lastValue);
//
//        $this->assertItemsHasFields($I, [
//            'id'
//        ]);
//
//        $I->sendGet("/v1/subscription/$mainSubscriptionId/events", [
//            'limit' => 2,
//            'lastValue' => $lastValue,
//        ]);
//        $lastValue = $this->assertItemNames($I, [
//            'Upcoming event 1',
//            'Upcoming event 0',
//        ]);
//        $I->assertNotNull($lastValue);
//
//        $I->sendGet("/v1/subscription/$mainSubscriptionId/events", [
//            'limit' => 2,
//            'lastValue' => $lastValue,
//        ]);
//        $lastValue = $this->assertItemNames($I, [
//            'Online event',
//        ]);
//        $I->assertNull($lastValue);
//
//        $this->assertItems($I, [
//            [
//                'title' => 'Online event',
//                'date' => time() - 3600,
//                'dateEnd' => time() + 3600,
//                'description' => 'Online description',
//                'participants' => [
//                    [
//                        'name' => 'alice_user_name',
//                    ],
//                    [
//                        'name' => 'bob_user_name',
//                    ],
//                ],
//                'listenerCount' => 1,
//                'interests' => [],
//                'language' => null,
//            ],
//        ]);
//    }
//
//    private function assertItems(ApiTester $I, array $items): ?string
//    {
//        $I->seeResponseContainsJson([
//            'response' => [
//                'items' => $items,
//            ],
//        ]);
//
//        return $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
//    }
//
//    private function assertItemNames(ApiTester $I, array $expectedNames): ?string
//    {
//        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
//        $I->assertNotNull($items);
//        $I->assertEquals($expectedNames, array_column($items, 'title'));
//
//        return $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
//    }
//
//    private function assertItemsHasFields(ApiTester $I, array $fields): void
//    {
//        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
//
//        foreach ($items as $itemKey => $item) {
//            foreach ($fields as $field) {
//                $I->assertArrayHasKey($field, $item, "Response item {$itemKey} must has field {$field}");
//            }
//        }
//    }
}
