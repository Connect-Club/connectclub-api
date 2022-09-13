<?php

namespace App\Tests\User;

use App\Entity\User;
use App\Entity\UserBlock;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;

class BlockCest extends BaseCest
{
    public function test(ApiTester $I)
    {
        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users', json_encode([$aliceId]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isBlocked' => false]);

        $I->sendPost('/v1/user/'.$aliceId.'/block');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(UserBlock::class, ['author' => ['id' => $mainId], 'blockedUser' => ['id' => $aliceId]]);

        $I->sendPost('/v2/users', json_encode([$aliceId]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isBlocked' => true]);

        $I->sendPost('/v1/user/'.$aliceId.'/unblock');
        $I->seeResponseCodeIs(HttpCode::OK);
        $userBlock = $I->grabEntityFromRepository(
            UserBlock::class,
            ['author' => ['id' => $mainId], 'blockedUser' => ['id' => $aliceId]]
        );
        $I->assertNotNull($userBlock->deletedAt);

        $I->sendPost('/v2/users', json_encode([$aliceId]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isBlocked' => false]);

        $I->sendPost('/v1/user/'.$aliceId.'/block');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(UserBlock::class, ['author' => ['id' => $mainId], 'blockedUser' => ['id' => $aliceId]]);

        $I->sendPost('/v2/users', json_encode([$aliceId]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isBlocked' => true]);
    }
}
