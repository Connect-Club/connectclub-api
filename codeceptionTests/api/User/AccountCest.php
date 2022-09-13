<?php

namespace App\Tests\User;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Controller\V2\User\AccountController;
use App\Entity\Activity\JoinDiscordActivity;
use App\Entity\Activity\WelcomeOnBoardingFriendActivity;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\SyncWithIntercomMessage;
use App\Message\UploadUserToElasticsearchMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Module\Doctrine2;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Mockery;
use Redis;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Traversable;

class AccountCest extends BaseCest
{
    private const SESSION_ID = 1234;

    public function changeState(ApiTester $I)
    {
        $bus = Mockery::mock(MessageBusInterface::class);
        $bus->shouldReceive('dispatch')
            ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $bus);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->state = User::STATE_NOT_INVITED;
                $main->phone = PhoneNumberUtil::getInstance()->parse('+79636417683');

                $manager->persist($main);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPatch('/v2/account/'.User::STATE_WAITING_LIST.'/state');
        $I->seeResponseCodeIs(HttpCode::OK);

        /** @var User $user */
        $user = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEquals(User::STATE_WAITING_LIST, $user->state);
    }

    public function testChangeStateVerified(ApiTester $I): void
    {
        ClockMock::withClockMock(1000);
        ClockMock::register(AccountController::class);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

                $main->state = User::STATE_INVITED;

                $main->phone = PhoneNumberUtil::getInstance()->parse('+79222222222');

                $manager->persist(new Invite($alice, $main->phone));

                $manager->flush();
            }
        });

        $bus = Mockery::mock(MessageBusInterface::class);
        $bus->shouldReceive('dispatch')
            ->with(Mockery::type(AmplitudeEventStatisticsMessage::class))
            ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)))
            ->times(1);
        $bus->shouldReceive('dispatch')
            ->with(Mockery::type(UploadUserToElasticsearchMessage::class))
            ->andReturn(new Envelope(Mockery::mock(UploadUserToElasticsearchMessage::class)))
            ->times(1);
        $bus->shouldReceive('dispatch')
            ->with(Mockery::type(SyncWithIntercomMessage::class))
            ->andReturn(new Envelope(Mockery::mock(SyncWithIntercomMessage::class)))
            ->times(1);
        $I->mockService(MessageBusInterface::class, $bus);

        $I->haveHttpHeader('amplSessionId', self::SESSION_ID);
        $I->haveHttpHeader('amplDeviceId', 'test-device-id');
        $I->haveHttpHeader('User-Agent', 'ios 1.2.64/app 9 (15.0)');

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPatch('/v2/account/' . User::STATE_VERIFIED .'/state');

        $I->seeResponseCodeIs(HttpCode::OK);

        /** @var User $main */
        $main = $I->grabEntityFromRepository(User::class, [
            'email' => self::MAIN_USER_EMAIL,
        ]);
        $I->seeInRepository(JoinDiscordActivity::class, [
            'user' => $main,
        ]);

        $redis = $this->redis($I);

        $I->assertEquals(self::SESSION_ID, $redis->get("user:$main->id:amplitude:sessionId"));
        $I->assertEquals('test-device-id', $redis->get("user:$main->id:amplitude:deviceId"));
        $I->assertEquals(['ios', '1.2.64'], json_decode($redis->get("user:$main->id:amplitude:appVersion")));
        $I->assertContains('new', $main->badges);
        $I->assertEquals(strtotime('+1 week', time()), $main->deleteNewBadgeAt);

        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);

        /** @var WelcomeOnBoardingFriendActivity $activity */
        $activity = $I->grabEntityFromRepository(WelcomeOnBoardingFriendActivity::class, [
            'user' => $alice,
        ]);

        $this->assertUsersEquals($I, [
            $main,
        ], $activity->nestedUsers);

        $this->assertUsersEquals($I, [
            $main,
            $alice
        ], $activity->videoRoom->invitedUsers);

        $I->assertTrue($activity->videoRoom->isPrivate);
        $I->assertEquals($main, $activity->videoRoom->forPersonallyOnBoarding);
    }

    public function changeLanguageTest(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->languages = [];

                $manager->flush();
            }
        });

        /** @var User $user */
        $user = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEmpty($user->languages);

        $en = $I->grabFromRepository(User\Language::class, 'id', ['code' => 'EN']);
        $ge = $I->grabFromRepository(User\Language::class, 'id', ['code' => 'GE']);
        $ru = $I->grabFromRepository(User\Language::class, 'id', ['code' => 'RU']);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendPatch('/v2/account', json_encode(['languageId' => $en]));
        $user = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEquals(['EN'], $user->languages);

        $I->sendPatch('/v2/account', json_encode([
            'languages' => [
                ['id' => $ge],
                ['id' => $en]
            ]
        ]));
        $user = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEquals(['GE', 'EN'], $user->languages);

        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->sendPatch('/v2/account', json_encode(['languages' => [['id' => $en]]]));
        $user = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEquals(['EN'], $user->languages);
    }

    //phpcs:ignore
    public function _after(ApiTester $I, Doctrine2 $module): void
    {
        parent::_after($I, $module);

        $this->redis($I)->flushAll();
    }

    private function redis(ApiTester $I): Redis
    {
        return $I->grabService(Redis::class);
    }

    private function assertUsersEquals(ApiTester $I, array $expected, Traversable $actual): void
    {
        $expected = array_column($expected, 'id');
        sort($expected);

        $actual = iterator_to_array($actual);

        $actual = array_column($actual, 'id');
        sort($actual);

        $I->assertEquals($expected, $actual);
    }
}
