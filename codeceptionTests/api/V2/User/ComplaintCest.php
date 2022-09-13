<?php

namespace App\Tests\V2\User;

use App\Controller\ErrorCode;
use App\Entity\User;
use App\Entity\User\Device;
use App\Message\HandleComplaintMessage;
use App\Message\SendNotificationMessage;
use App\Service\SlackClient;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Mockery;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ComplaintCest extends BaseCest
{
    public function testCreateComplaintAbuserNotFound(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/complaint/0', json_encode(['reason' => 'Reason of complaint']));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_COMPLAINT_ABUSER_NOT_FOUND]]);
    }

    public function testCreateValidation(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/complaint/0', json_encode(['reason' => '']));
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeResponseContainsJson(['errors' => ['reason:cannot_be_empty']]);
    }

    public function testCreate(ApiTester $I)
    {
        $bobId = $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);
        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')->withArgs(function ($message) {
            return $message instanceof HandleComplaintMessage;
        })->andReturn(new Envelope(Mockery::mock(HandleComplaintMessage::class)));
        $I->mockService(MessageBusInterface::class, $busMock);

        $slackClientMock = Mockery::mock(SlackClient::class);
        $slackClientMock->shouldReceive('sendMessage')->with(
            'meeting-complaint',
            'Received user complaint'.PHP_EOL.
            'Author: main_user_name main_user_surname (id '.$mainId.')'.PHP_EOL.
            'Abuser: bob_user_name bob_user_surname (id '.$bobId.')'.PHP_EOL.
            'Reason: Reason of complaint'.PHP_EOL.
            'Description: Desc',
            null,
            false
        )->andReturn(['ts' => 'test']);
        $I->mockService(SlackClient::class, $slackClientMock);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/complaint/' . $bobId, json_encode([
            'reason' => 'Reason of complaint',
            'description' => 'Desc'
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->seeInRepository(User\Complaint::class, [
            'abuser' => [
                'email' => self::BOB_USER_EMAIL
            ],
            'author' => [
                'email' => self::MAIN_USER_EMAIL
            ],
            'reason' => 'Reason of complaint',
            'description' => 'Desc'
        ]);
    }
}
