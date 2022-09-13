<?php

namespace App\Tests\Event;

use App\DataFixtures\AccessTokenFixture;
use App\Entity\Community\Community;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\User;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\UserFixtureTrait;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\PhpUnit\ClockMock;

class PersonalEventScheduleCest extends BaseCest
{
    use EventInterestTrait;

    public function testPersonal(ApiTester $I)
    {
        ClockMock::withClockMock(true);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            private int $eventsCount = 0;

            public function load(ObjectManager $manager)
            {
                $this->entityManager = $manager;

                /** @var User $alice */
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                /** @var User $bob */
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $eventSchedule = new EventSchedule(
                    $alice,
                    'Owned by Alice',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $manager->persist($eventSchedule);


                $community = new Community($alice, 'test community 1');
                $this->entityManager->persist($community);

                $eventSchedule = new EventSchedule(
                    $alice,
                    'Owned by Alice with started video room',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $community->videoRoom->eventSchedule = $eventSchedule;
                $community->videoRoom->startedAt = time();
                $manager->persist($eventSchedule);

                $eventSchedule = new EventSchedule(
                    $bob,
                    'Owned by Bob',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $manager->persist($eventSchedule);

                $eventSchedule = new EventSchedule(
                    $bob,
                    'Owned by Bob, Alice participate',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleParticipant($eventSchedule, $alice));

                $eventSchedule = new EventSchedule(
                    $bob,
                    'Owned by Bob, Alice special guest',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleParticipant($eventSchedule, $alice, true));

                $eventSchedule = new EventSchedule(
                    $bob,
                    'Owned by Bob, Alice subscribed',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleSubscription($eventSchedule, $alice));

                $eventSchedule = new EventSchedule(
                    $bob,
                    'Owned by Bob, private, Alice participate',
                    $this->nextEventScheduleDateTime(),
                    ''
                );
                $manager->persist($eventSchedule);
                $manager->persist(new EventScheduleSubscription($eventSchedule, $alice));

                $manager->flush();
            }

            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            private function nextEventScheduleDateTime(): int
            {
                $time = time() + 3601 + $this->eventsCount;
                $this->eventsCount++;

                return $time;
            }
        }, false);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v1/event-schedule/personal?limit=20');
        $I->seeResponseCodeIs(HttpCode::OK);

        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];

        $I->assertNull($lastValue);

        $this->assertItemOrder($I, [
            'Owned by Alice',
            'Owned by Bob, Alice participate',
            'Owned by Bob, Alice special guest',
            'Owned by Bob, Alice subscribed',
            'Owned by Bob, private, Alice participate',
        ], $items);
    }

    private function assertItemOrder(ApiTester $I, array $expectedOrder, array $actualItems): void
    {
        $actualOrder = array_column(array_slice($actualItems, 0, count($expectedOrder)), 'title');

        $I->assertEquals($expectedOrder, $actualOrder);
    }
}
