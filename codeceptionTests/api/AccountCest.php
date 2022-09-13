<?php

namespace App\Tests;

use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use App\DataFixtures\AccessTokenFixture;
use App\DataFixtures\VideoRoomFixture;
use App\Entity\Location\City;
use App\Entity\Location\Country;
use App\Repository\Location\CityRepository;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use MaxMind\Db\Reader;

class AccountCest extends BaseCest
{
    const LOGOUT_PATH = 'v1/account/logout';

    public function currentWithoutAuthHeader(ApiTester $I)
    {
        $I->sendGet(self::CURRENT_PATH);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::UNAUTHORIZED);
        $I->seeResponseContainsJson(
            [
                'error' => 'access_denied',
                'error_description' => 'OAuth2 authentication required'
            ]
        );
    }

    public function currentWithWrongHeaderValue(ApiTester $I)
    {
        $I->amBearerAuthenticated('xxx');
        $I->sendGet(self::CURRENT_PATH);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::UNAUTHORIZED);
        $I->seeResponseMatchesJsonTypeStrict(
            [
                'error' => 'string',
                'error_description' => 'string',
            ],
            false
        );
        $I->seeResponseContainsJson(
            [
                'error' => 'invalid_grant',
                'error_description' => 'The access token provided is invalid.'
            ]
        );
    }

    public function currentTest(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet(self::CURRENT_PATH);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict(
            [
                'id' => 'integer',
                'email' => 'string',
                'country' => [
                    'id' => 'integer',
                    'name' => 'string'
                ],
                'city' => [
                    'id' => 'integer',
                    'name' => 'string'
                ],
                'createdAt' => 'integer',
                'name' => 'string|null',
                'surname' => 'string|null',
                'avatarSrc' => 'string|null',
                'about' => 'string|null',
                'deleted' => 'boolean',
                'interests' => 'array',
                'badges' => 'array',
                'shortBio' => 'string|null',
                'longBio' => 'string|null',
            ]
        );

        $I->seeResponseContainsJson([
            'email' => self::MAIN_USER_EMAIL,
        ]);
    }

    public function logout(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::LOGOUT_ACCESS_TOKEN);
        $I->sendPost(self::LOGOUT_PATH);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->sendPost(self::LOGOUT_PATH);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::UNAUTHORIZED);
        $I->seeResponseContainsJson(
            [
                'error' => 'invalid_grant',
                'error_description' => 'The access token provided is invalid.'
            ]
        );
    }
}
