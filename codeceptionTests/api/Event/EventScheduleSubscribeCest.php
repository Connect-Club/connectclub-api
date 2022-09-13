<?php

namespace App\Tests\Event;

use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleSubscription;
use App\Repository\Event\EventScheduleSubscriptionRepository;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EventScheduleSubscribeCest extends BaseCest
{
    public function testSubscribeUnsubscribe(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $eventSchedule = new EventSchedule($main, 'eventSchedule', time(), null);
                $manager->persist($eventSchedule);

                $manager->flush();
            }
        });

        /** @var EventSchedule $eventSchedule */
        $eventSchedule = $I->grabEntityFromRepository(EventSchedule::class, ['name' => 'eventSchedule']);
        $id = $eventSchedule->id->toString();

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost('/v1/event-schedule/'.$id.'/subscribe');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(EventScheduleSubscription::class, [
            'eventSchedule' => [
                'id' => $id,
            ],
            'user' => [
                'email' => BaseCest::ALICE_USER_EMAIL,
            ],
        ]);

        $I->sendGet('/v1/event-schedule/'.$id);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isSubscribed' => true]);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost('/v1/event-schedule/'.$id.'/unsubscribe');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInRepository(EventScheduleSubscription::class, [
            'eventSchedule' => [
                'id' => $id,
            ],
            'user' => [
                'email' => BaseCest::ALICE_USER_EMAIL,
            ],
        ]);

        $I->sendGet('/v1/event-schedule/'.$id);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isSubscribed' => false]);
    }
}
