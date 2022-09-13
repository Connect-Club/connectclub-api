<?php

namespace App\Tests\VideoRoom;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Entity\User;
use App\Service\SlackClient;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Mockery;

class VideoRoomStatisticsCest extends BaseCest
{
    public function statisticsCest(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $mainId = (string) $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $bobId = (string) $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);
        $aliceId = (string) $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $mock = Mockery::mock(SlackClient::class);
        $mock->shouldReceive('sendMessage')->with(
            $_ENV['SLACK_CHANNEL_VIDEO_ROOM_STATISTICS_NAME'],
            "Video room description:\n".
            //phpcs:ignore
            "main_user_name main_user_surname: alice_user_name alice_user_surname (id $aliceId) - 03:15 min, bob_user_name bob_user_surname (id $bobId) - 00:20 min\n".
            //phpcs:ignore
            "bob_user_name bob_user_surname: alice_user_name alice_user_surname (id $aliceId) - 00:05 min, main_user_name main_user_surname (id $mainId) - 00:20 min\n"
        )->once();
        $I->mockService(SlackClient::class, $mock);

        $I->sendPost('/v1/video-room/statistics', json_encode([
            'roomname' => self::VIDEO_ROOM_TEST_NAME,
            'stat' => [
                $mainId => [
                    $aliceId => 195,
                    $bobId => 20
                ],
                $bobId => [
                    $aliceId => 5,
                    $mainId => 20
                ],
                $aliceId => []
            ]
        ]));

        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function statisticsEmptyStatsCest(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $mainId = (string) $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $bobId = (string) $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);
        $aliceId = (string) $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $mock = Mockery::mock(SlackClient::class);
        $mock->shouldReceive('sendMessage')->never();
        $I->mockService(SlackClient::class, $mock);

        $I->sendPost('/v1/video-room/statistics', json_encode([
            'roomname' => self::VIDEO_ROOM_TEST_NAME,
            'stat' => [
                $bobId => [],
                $aliceId => []
            ]
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPost('/v1/video-room/statistics', json_encode([
            'roomname' => self::VIDEO_ROOM_TEST_NAME,
            'stat' => [
                $bobId => [],
            ]
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPost('/v1/video-room/statistics', json_encode([
            'roomname' => self::VIDEO_ROOM_TEST_NAME,
            'stat' => []
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
