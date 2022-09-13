<?php

namespace App\Tests\Event;

use App\DataFixtures\AccessTokenFixture;
use App\DTO\V1\Subscription\Event;
use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Community\Community;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Follow\Follow;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\User;
use App\Repository\Event\EventScheduleRepository;
use App\Service\MatchingClient;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\UserFixtureTrait;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;

class UpcomingEventScheduleCest extends BaseCest
{
    use EventInterestTrait;

    public function testUpcoming(ApiTester $I)
    {
        ClockMock::withClockMock(true);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            use UserFixtureTrait;

            private int $eventsCount = 0;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                foreach ($manager->getRepository('App:Follow\Follow') as $follow) {
                    $manager->remove($follow);
                }

                /** @var User $main */
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                /** @var User $alice */
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                /** @var User $bob */
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                /** @var User $mike */
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mike->state = User::STATE_VERIFIED;
                $manager->persist($mike);
                $manager->flush();

                $main->clearInterests();
                $alice->clearInterests();
                $bob->clearInterests();

                $interestGroup = new InterestGroup('Group');
                $manager->persist($interestGroup);
                $interestA = new Interest($interestGroup, 'Interest A', 0, false);
                $manager->persist($interestA);
                $interestB = new Interest($interestGroup, 'Interest B', 0, false);
                $manager->persist($interestB);
                $interestC = new Interest($interestGroup, 'Interest C', 0, false);
                $manager->persist($interestC);

                $languageInterestEnglish = new User\Language('English', 'EN');
                $manager->persist($languageInterestEnglish);

                $languageInterestRussia = new User\Language('Russia', 'RU');
                $manager->persist($languageInterestRussia);

                $eventSchedule = new EventSchedule(
                    $alice,
                    'Main event schedule 1',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $eventSchedule->createdAt = 1618579262;
                $eventSchedule->addInterest($interestA);
                $eventSchedule->addInterest($interestB);
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleParticipant($eventSchedule, $main));

                $eventSchedule = new EventSchedule($alice, 'Alice event schedule 1', time() - 3600, '');
                $eventSchedule->createdAt = 1618579263;
                $eventSchedule->addInterest($interestB);
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleParticipant($eventSchedule, $alice));

                $peterClub = new Club($this->createUser('Peter'), 'Peter Club');
                $manager->persist($peterClub);
                $john = $this->createUser('John');
                $johnClub = new Club($john, 'John Club');
                $manager->persist($johnClub);

                $manager->persist(new ClubParticipant($johnClub, $main, $john));

                $es = $this->createEventScheduleWithStartedVideoRoom($mike);
                $es->id = Uuid::fromString('ed1ec433-7261-4ed6-9468-578d819f65ae');
                $es = $this->createEventScheduleWithStartedPrivateVideoRoom($mike);
                $es->id = Uuid::fromString('ed1ec433-7261-4ed6-9468-578d819f66ae');
                $es = $this->createEventScheduleWithPrivateVideoRoom($mike);
                $es->id = Uuid::fromString('ed1ec433-7261-4ed6-9468-578d819f67ae');
                $es = $this->createEventScheduleWithVideoRoom($mike);
                $es->id = Uuid::fromString('ed1ec433-7261-4ed6-9468-578d819f68ae');
                $es =  $this->createClubEventSchedule($peterClub);
                $es->id = Uuid::fromString('ed1ec433-7261-4ed6-9468-578d819f69ae');
                $es = $this->createClubEventSchedule($johnClub);
                $es->id = Uuid::fromString('ed1ec433-7261-4ed6-9468-578d819f70ae');

                $eventSchedule = new EventSchedule(
                    $alice,
                    'Alice event schedule 2',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $eventSchedule->createdAt = 1618579264;
                $eventSchedule->addInterest($interestC);
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleParticipant($eventSchedule, $alice));

                $eventSchedule = new EventSchedule(
                    $bob,
                    'Bob event schedule 1',
                    $this->nextEventScheduleDateTime(),
                    '',
                    $languageInterestEnglish
                );
                $eventSchedule->createdAt = 1618579264;
                $eventSchedule->addInterest($interestA);
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleParticipant($eventSchedule, $bob));

                $eventSchedule = new EventSchedule(
                    $mike,
                    'Mike event schedule 1',
                    $this->nextEventScheduleDateTime(),
                    '',
                    $languageInterestRussia
                );
                $eventSchedule->createdAt = 1618579265;
                $eventSchedule->language = $languageInterestRussia;
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleParticipant($eventSchedule, $mike));
                $manager->persist(new EventScheduleParticipant($eventSchedule, $alice));

                //Main follows aliceFollow
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($main, $mike));
                $manager->persist(new Follow($main, $bob));

                /*
                 * Main + Bob   = 2 mutual interests
                 * Main + Mike  = 1 mutual interest
                 * Main + Alice = 0 mutual interest, but main follows alice
                 */
                $main->addInterest($interestA);
                $main->addInterest($interestB);
                $main->addNativeLanguage($languageInterestEnglish);
                $bob->addInterest($interestA);
                $bob->addInterest($interestB);
                $mike->addInterest($interestA);

                $manager->persist($main);
                $manager->persist($bob);
                $manager->persist($mike);

                $manager->flush();
            }

            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            private function createEventScheduleWithVideoRoom(User $user): EventSchedule
            {
                $community = new Community($user, 'test community 1');
                $this->entityManager->persist($community);

                $eventSchedule = new EventSchedule(
                    $user,
                    'Event schedule with video room',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $eventSchedule->createdAt = 1618579265;

                $community->videoRoom->eventSchedule = $eventSchedule;

                $this->entityManager->persist($eventSchedule);
                $this->entityManager->persist(new EventScheduleParticipant($eventSchedule, $user));

                return $eventSchedule;
            }

            private function createClubEventSchedule(Club $club): EventSchedule
            {
                $community = new Community($club->owner, "$club->title community");
                $this->entityManager->persist($community);

                $eventSchedule = new EventSchedule(
                    $club->owner,
                    "$club->title Event schedule",
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $eventSchedule->createdAt = 1618579265;
                $eventSchedule->club = $club;

                $community->videoRoom->eventSchedule = $eventSchedule;

                $this->entityManager->persist($eventSchedule);
                $this->entityManager->persist(new EventScheduleParticipant($eventSchedule, $club->owner));

                return $eventSchedule;
            }

            private function createEventScheduleWithStartedVideoRoom(User $user): EventSchedule
            {
                $community = new Community($user, 'test community 2');
                $this->entityManager->persist($community);

                $dateTime = $this->nextEventScheduleDateTime();

                $eventSchedule = new EventSchedule(
                    $user,
                    'Event schedule with started video room',
                    $dateTime,
                    ''
                );
                $eventSchedule->createdAt = 1618579265;

                $community->videoRoom->eventSchedule = $eventSchedule;
                $community->videoRoom->startedAt = $dateTime + 5;

                $this->entityManager->persist($eventSchedule);
                $this->entityManager->persist(new EventScheduleParticipant($eventSchedule, $user));

                return $eventSchedule;
            }

            private function createEventScheduleWithStartedPrivateVideoRoom(User $user): EventSchedule
            {
                $community = new Community($user, 'test community 3');
                $this->entityManager->persist($community);

                $dateTime = $this->nextEventScheduleDateTime();
                $eventSchedule = new EventSchedule(
                    $user,
                    'Event schedule with started private video room',
                    $dateTime,
                    ''
                );
                $eventSchedule->createdAt = 1618579265;

                $community->videoRoom->eventSchedule = $eventSchedule;
                $community->videoRoom->startedAt = $dateTime + 4;
                $community->videoRoom->isPrivate = true;

                $this->entityManager->persist($eventSchedule);
                $this->entityManager->persist(new EventScheduleParticipant($eventSchedule, $user));

                return $eventSchedule;
            }

            private function createEventScheduleWithPrivateVideoRoom(User $user): EventSchedule
            {
                $community = new Community($user, 'test community 4');
                $this->entityManager->persist($community);

                $eventSchedule = new EventSchedule(
                    $user,
                    'Event schedule with private video room',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $eventSchedule->createdAt = 1618579265;

                $community->videoRoom->eventSchedule = $eventSchedule;
                $community->videoRoom->isPrivate = true;

                $this->entityManager->persist($eventSchedule);
                $this->entityManager->persist(new EventScheduleParticipant($eventSchedule, $user));

                return $eventSchedule;
            }

            private function nextEventScheduleDateTime(): int
            {
                $time = time() + 3601 + $this->eventsCount;
                $this->eventsCount++;

                return $time;
            }
        }, false);

        $_ENV['STAGE'] = 1;

        $matchingMock = Mockery::mock(MatchingClient::class);
        $matchingMock->shouldReceive('findEventScheduleForUser')->andReturn([
            'data' => [
                ['id' => 'ed1ec433-7261-4ed6-9468-578d819f70ae'],
                ['id' => 'ed1ec433-7261-4ed6-9468-578d819f65ae'],
                ['id' => 'ed1ec433-7261-4ed6-9468-578d819f66ae'],
                ['id' => 'ed1ec433-7261-4ed6-9468-578d819f67ae'],
                ['id' => 'ed1ec433-7261-4ed6-9468-578d819f68ae'],
                ['id' => 'ed1ec433-7261-4ed6-9468-578d819f69ae'],
            ],
            'lastValue' => null,
        ])->once();
        $I->mockService(MatchingClient::class, $matchingMock);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/event-schedule/upcoming?limit=20');
        $I->seeResponseCodeIs(HttpCode::OK);

        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];

        $I->assertNull($lastValue);

        $this->assertItemOrder($I, [
            'John Club Event schedule',
            'Event schedule with started video room',
            'Event schedule with started private video room',
            'Event schedule with private video room',
            'Event schedule with video room',
            'Peter Club Event schedule',
        ], $items);

        $this->assertInterests($I, [
            'Event schedule with started private video room' => [],
            'Event schedule with private video room' => [],
            'Event schedule with video room' => [],
            'John Club Event schedule' => [],
            'Peter Club Event schedule' => [],
            'Event schedule with started video room' => [],
        ]);
    }

    private function assertItemOrder(ApiTester $I, array $expectedOrder, array $actualItems): void
    {
        $actualOrder = array_column(array_slice($actualItems, 0, count($expectedOrder)), 'title');

        $I->assertEquals($expectedOrder, $actualOrder);
    }
}
