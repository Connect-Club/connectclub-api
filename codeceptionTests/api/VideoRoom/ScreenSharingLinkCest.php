<?php

namespace App\Tests\VideoRoom;

use App\Entity\VideoRoom\ScreenShareToken;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;

class ScreenSharingLinkCest extends BaseCest
{
    public function createSharingLink(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/video-room/'.self::VIDEO_ROOM_TEST_NAME.'/sharing');
        $I->seeResponseCodeIs(HttpCode::OK);

        $token = $I->grabEntityFromRepository(ScreenShareToken::class, [
            'videoRoom' => [
                'community' => [
                    'name' => self::VIDEO_ROOM_TEST_NAME
                ]
            ]
        ]);
        $I->assertNotNull($token);

        $I->amBearerAuthenticated(null);
        $link = $I->grabDataFromResponseByJsonPath('$.response.link')[0];
        $page = str_replace($_ENV['SCREEN_SHARING_HOST'], '', $link);

        $I->assertEquals('/s/'.$token->token, $page);
    }
}
