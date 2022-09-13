<?php

namespace App\Tests\Event;

use App\Entity\Event\RequestApprovePrivateMeetingChange;
use App\Entity\Follow\Follow;
use App\Entity\User;
use App\Entity\User\Device;
use App\Message\SendNotificationMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PrivateEventCest extends BaseCest
{
    public function acceptancePrivateEventTest(ApiTester $I)
    {
        ClockMock::withClockMock(1650452219);

        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $manager->persist(new Device(
                    Uuid::uuid4(),
                    $alice,
                    Device::TYPE_IOS_REACT,
                    'token',
                    null,
                    'RU'
                ));
                $manager->persist(new Follow($main, $alice));
                $manager->persist(new Follow($alice, $main));

                $manager->flush();
            }
        }, true);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                //phpcs:ignore
                $message->options['title'] == 'main_user_name m. arranged meeting' &&
                $message->message == 'Tap to review time and date 👉' &&
                $message->options['type'] == 'event-schedule';
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();
        $I->mockService(MessageBusInterface::class, $busMock);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/event-schedule', json_encode([
            'title' => 'Hey',
            'date' => time() + 3600,
            'participants' => [
                ['id' => (string) $alice->id]
            ],
            'description' => 'My new event with my dear friends',
            'isPrivate' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $eventScheduleId = $I->grabDataFromResponse('id');

        $I->seeInRepository(RequestApprovePrivateMeetingChange::class, [
            'eventSchedule' => ['name' => 'Hey'],
            'user' => ['email' => BaseCest::ALICE_USER_EMAIL],
            'reviewed' => false
        ]);

        $I->sendPost('/v1/event-schedule/'.$eventScheduleId.'/approve');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errors' => ['not_found_requirement_for_approve_changes']]);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost('/v1/event-schedule/'.$eventScheduleId.'/approve');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInRepository(RequestApprovePrivateMeetingChange::class, [
            'eventSchedule' => ['name' => 'Hey'],
            'user' => ['email' => BaseCest::ALICE_USER_EMAIL],
            'reviewed' => false
        ]);

        $I->sendPatch('/v1/event-schedule/'.$eventScheduleId, json_encode([
            'title' => 'Hey',
            'date' => time() + 3601,
            'participants' => [
                ['id' => (string) $alice->id],
                ['id' => (string) $main->id],
            ],
            'description' => 'My new event with my dear friends',
            'isPrivate' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInRepository(RequestApprovePrivateMeetingChange::class, [
            'eventSchedule' => ['name' => 'Hey'],
            'user' => ['email' => BaseCest::ALICE_USER_EMAIL],
            'reviewed' => false
        ]);
        $I->seeInRepository(RequestApprovePrivateMeetingChange::class, [
            'eventSchedule' => ['name' => 'Hey'],
            'user' => ['email' => BaseCest::MAIN_USER_EMAIL],
            'reviewed' => false
        ]);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                //phpcs:ignore
                $message->options['title'] == 'main_user_name m. approved meeting' &&
                $message->message == 'Don\'t miss out on "Hey". Wednesday, April 20 at 02:57 PM' &&
                $message->options['type'] == 'event-schedule';
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();
        $I->mockService(MessageBusInterface::class, $busMock);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/event-schedule/'.$eventScheduleId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['response' => ['needApprove' => true, 'isPrivate' => true]]);

        $I->sendPost('/v1/event-schedule/'.$eventScheduleId.'/approve');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInRepository(RequestApprovePrivateMeetingChange::class, [
            'eventSchedule' => ['name' => 'Hey'],
            'user' => ['email' => BaseCest::MAIN_USER_EMAIL],
            'reviewed' => false
        ]);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof SendNotificationMessage &&
                $message->platformType == Device::TYPE_IOS_REACT &&
                //phpcs:ignore
                $message->options['title'] == 'A meeting cancellation' &&
                $message->message == 'Private meeting “Hey” on Wednesday, April 20 at 02:57 PM has been cancelled' &&
                $message->options['type'] == 'event-schedule';
        })->andReturn(new Envelope(Mockery::mock(SendNotificationMessage::class)))->once();
        $I->mockService(MessageBusInterface::class, $busMock);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/event-schedule/'.$eventScheduleId.'/cancel');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInRepository(RequestApprovePrivateMeetingChange::class, [
            'eventSchedule' => ['name' => 'Hey'],
            'user' => ['email' => BaseCest::MAIN_USER_EMAIL],
            'reviewed' => false
        ]);
    }
}
