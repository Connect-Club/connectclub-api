<?php

namespace App\Tests\Auth;

use App\Entity\User;
use App\Service\JwtToken;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Ramsey\Uuid\Uuid;

class AuthCest extends BaseCest
{
    public function refresh(ApiTester $I)
    {
        $I->sendPost(
            self::TOKEN_PATH,
            [
                'grant_type' => 'refresh_token',
                'client_id' => self::OAUTH_CLIENT_ID,
                'client_secret' => self::OAUTH_CLIENT_SECRET,
                'refresh_token' => self::REFRESH_TOKEN,
            ]
        );
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseContainsJson(
            [
                'event' => 'login',
            ]
        );
    }

    public function testUserRoles(ApiTester $I)
    {
        $_ENV['DISABLE_SMS_IP_VERIFICATION'] = 1;

        $jwtTokenServiceMock = \Mockery::mock(JwtToken::class);
        $jwtTokenServiceMock->shouldReceive('getJWTClaim')->andReturn(Uuid::uuid4()->toString());
        $I->mockService(JwtToken::class, $jwtTokenServiceMock);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository(User::class);
                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $main->phone = PhoneNumberUtil::getInstance()->parse('+79070000000');
                $manager->flush();
            }
        });

        $I->sendPOST('/v1/sms/verification', json_encode(['phone' => '+79070000000']));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendGET('/oauth/v2/token', [
            'grant_type' => 'https://connect.club/sms',
            'phone' => '+79070000000',
            'code' => '1111',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
        ]);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals('admin', $I->grabDataFromResponseByJsonPath('$.scope')[0]);
    }
}
