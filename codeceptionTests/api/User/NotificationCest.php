<?php

namespace App\Tests\User;

use App\Entity\Notification\NotificationStatistic;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;

class NotificationCest extends BaseCest
{
    public function statistics(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/notification/statistic/join-the-room');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(NotificationStatistic::class, [
            'code' => 'join-the-room',
            'clickedBy' => [
                'email' => self::MAIN_USER_EMAIL
            ]
        ]);
    }
}
