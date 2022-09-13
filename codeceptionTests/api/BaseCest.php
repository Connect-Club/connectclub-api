<?php

namespace App\Tests;

use App\Tests\Module\Doctrine2;
use Doctrine\ORM\EntityManagerInterface;
use Mockery;
use Symfony\Bridge\PhpUnit\ClockMock;

class BaseCest
{
    const UNITY_SERVER_ACCESS_TOKEN = 'Test_unity_server_access_token';
    const MAIN_ACCESS_TOKEN = 'Test_access_token_for_user_main';
    const BOB_ACCESS_TOKEN = 'Test_access_token_for_user_bob';
    const ALICE_ACCESS_TOKEN = 'Test_access_token_for_user_alice';
    const MIKE_ACCESS_TOKEN = 'Test_access_token_for_user_mike';
    const LOGOUT_ACCESS_TOKEN = 'Test_access_token_for_user_logout';
    const REFRESH_TOKEN = 'Test_refresh_token_for_user';
    const MAIN_USER_EMAIL = 'test@test.ru';
    const MAIN_USER_NAME = 'main_user_name';
    const MAIN_USER_SURNAME = 'main_user_surname';
    const BOB_USER_EMAIL = 'bob@test.ru';
    const MIKE_USER_EMAIL = 'mike@test.ru';
    const BOB_USER_NAME = 'bob_user_name';
    const BOB_USER_SURNAME = 'bob_user_surname';
    const ALICE_USER_EMAIL = 'alice@test.ru';
    const ALICE_USER_NAME = 'alice_user_name';
    const ALICE_USER_SURNAME = 'alice_user_surname';
    const TOKEN_PATH = 'oauth/v2/token';
    const OAUTH_CLIENT_ID = '3_3u3bpqxw736s4kgo0gsco4kw48gos800gscg4s4w8w80oogc8c';
    const OAUTH_CLIENT_SECRET = '6cja0geitwsok4gckw0cc0c04sc0sgwgo8kggcoc08wocsw8wg';
    const CURRENT_PATH = 'v1/account/current';
    const TIME_IN_PAST = 1584641665;
    const ACCESS_TOKEN_TTL = 1209600;
    const FACEBOOK_GRANT_TYPE = 'https://connect.club/facebook';
    const GOOGLE_GRANT_TYPE = 'https://connect.club/google';
    const APPLE_GRANT_TYPE = 'https://connect.club/apple';
    const FACEBOOK_USER_FIXTURE_ID = 'facebook_user_fixture_id';
    const FACEBOOK_USER_FIXTURE_EMAIL = 'facebookUserFixture@facebook.com';
    const FACEBOOK_USER_FIXTURE_NAME = 'facebookUserFixtureName';
    const FACEBOOK_USER_FIXTURE_SURNAME = 'facebookUserFixtureSurname';
    const GOOGLE_USER_FIXTURE_SUB = 'google_user_fixture_sub';
    const GOOGLE_USER_FIXTURE_EMAIL = 'googleUserFixture@google.com';
    const GOOGLE_USER_FIXTURE_NAME = 'googleUserFixtureName';
    const GOOGLE_USER_FIXTURE_SURNAME = 'googleUserFixtureSurname';
    const VIDEO_ROOM_TEST_NAME = 'video_room_name';
    const VIDEO_ROOM_BOB_NAME = 'video_room_bob';

    //phpcs:ignore
    public function _before()
    {
        ClockMock::register(static::class);
    }

    //phpcs:ignore
    public function _passed(ApiTester $I, Doctrine2 $doctrine2)
    {
        Mockery::close();
//
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $I->grabService(EntityManagerInterface::class);

        if ($doctrine2->_getConfig('cleanup')) {
            $I->assertEquals(1, $entityManager->getConnection()->getTransactionNestingLevel());
        } else {
            $I->assertEquals(0, $entityManager->getConnection()->getTransactionNestingLevel());
        }
    }

    //phpcs:ignore
    public function _failed(ApiTester $I)
    {
        $container = Mockery::getContainer();
        Mockery::resetContainer();

        $container->mockery_close();
    }

    //phpcs:ignore
    public function _after(ApiTester $I, Doctrine2 $module): void
    {
        $I->cleanupMockedServices();
        ClockMock::withClockMock(false);
    }

    protected function noCleanup(Doctrine2 $module)
    {
        $module->_reconfigure(['cleanup' => false]);
    }
}
