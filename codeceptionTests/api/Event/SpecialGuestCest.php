<?php

namespace App\Tests\Event;

use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class SpecialGuestCest extends BaseCest
{
    public function testSortingSpecialGuestsEventSchedule(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository('App:User');

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $eventSchedule = new EventSchedule($main, 'Event schedule', time(), 'Description');
                $eventSchedule->id = Uuid::fromString('1279bb74-2001-4f51-a462-acdf23056e0d');

                $manager->persist(new EventScheduleParticipant($eventSchedule, $main));
                $manager->persist(new EventScheduleParticipant($eventSchedule, $bob));
                $manager->persist(new EventScheduleParticipant($eventSchedule, $alice, true));
                $manager->persist(new EventScheduleParticipant($eventSchedule, $mike));

                $manager->persist($eventSchedule);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/event-schedule/1279bb74-2001-4f51-a462-acdf23056e0d');
        $I->seeResponseCodeIs(HttpCode::OK);

        //Special guest
        $I->assertEquals('alice_user_name', $I->grabDataFromResponseByJsonPath('$.response.participants[0].name')[0]);
        //Owner
        $I->assertEquals('main_user_name', $I->grabDataFromResponseByJsonPath('$.response.participants[1].name')[0]);
        //Simple moderator
        $I->assertEquals('bob_user_name', $I->grabDataFromResponseByJsonPath('$.response.participants[2].name')[0]);
        //Simple moderator
        $I->assertEquals('Mike', $I->grabDataFromResponseByJsonPath('$.response.participants[3].name')[0]);
    }
}
