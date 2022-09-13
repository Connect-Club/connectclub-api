<?php

namespace App\Tests\User;

use App\Controller\ErrorCode;
use App\Entity\Chat\AbstractChat;
use App\Entity\Chat\GroupChat;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Jabber\JabberClient;
use App\Service\JitsiEndpointManager;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Mockery;

class UserBanCest extends BaseCest
{
    public function testUserBanAndUnbanCest(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        /** @var User $bob */
        $bob = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]);

        $I->assertNull($bob->bannedAt);
//
//        $jitsiEndpointManagerMock = Mockery::mock(JitsiEndpointManager::class);
//        $jitsiEndpointManagerMock->shouldReceive('disconnectUserFromRoom')->with(
//            Mockery::on(fn(User $user) => $user->id == $bob->id),
//            Mockery::on(fn(VideoRoom $videoRoom) => $videoRoom->community->name === BaseCest::VIDEO_ROOM_BOB_NAME)
//        )->once();
//        $I->mockService(JitsiEndpointManager::class, $jitsiEndpointManagerMock);

        $I->sendPOST('/v1/user/'.$bob->id.'/ban', json_encode(['comment' => 'You are banned']));
        $I->seeResponseCodeIs(HttpCode::OK);
        /** @var User $bob */
        $bob = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]);
        $I->assertNotNull($bob->bannedAt);

        foreach (['/v2/account', '/v1/event/online'] as $endpointUrl) {
            $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
            $I->sendGET($endpointUrl);
            $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        }

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPOST('/v1/user/'.$bob->id.'/unban');
        $I->seeResponseCodeIs(HttpCode::OK);
        /** @var User $bob */
        $bob = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]);
        $I->assertNull($bob->bannedAt);
    }
}
