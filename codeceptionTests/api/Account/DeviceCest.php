<?php

namespace App\Tests\Account;

use App\Entity\User;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Ramsey\Uuid\Uuid;

class DeviceCest extends BaseCest
{
    public function testAddNewDeviceAndLoadNewPushToken(ApiTester $I)
    {
        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $devices = $I->grabEntitiesFromRepository(User\Device::class, ['user' => ['email' => self::MAIN_USER_EMAIL]]);
        $I->assertCount(0, $devices);

        $I->sendPOST('/v1/device', json_encode([
            'deviceId' => 'a2476e7a-91f5-11ea-bb37-0242ac130002',
            'locale' => 'RU',
            'pushToken' => '070797d2-252a-4fbf-ae50-f44bcd31b6ed',
            'timeZone' => 'Europe/Moscow'
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        //Push new device
        $devices = $I->grabEntitiesFromRepository(User\Device::class, ['user' => ['email' => self::MAIN_USER_EMAIL]]);
        $I->assertCount(1, $devices);
        $I->seeInRepository(User\Device::class, [
            'id' => $mainId.'_a2476e7a-91f5-11ea-bb37-0242ac130002',
            'user' => [
                'email' => self::MAIN_USER_EMAIL
            ],
            'token' => '070797d2-252a-4fbf-ae50-f44bcd31b6ed',
            'locale' => 'RU',
            'timeZone' => 'Europe/Moscow',
        ]);

        //Push device with new token
        $I->sendPOST('/v1/device', json_encode([
            'deviceId' => 'a2476e7a-91f5-11ea-bb37-0242ac130002',
            'locale' => 'RU',
            'pushToken' => '111620f9-848a-49be-a7a2-8fdaa01856e4',
            'timeZone' => 'Europe/Moscow'
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $devices = $I->grabEntitiesFromRepository(User\Device::class, ['user' => ['email' => self::MAIN_USER_EMAIL]]);
        $I->assertCount(1, $devices);
        $I->seeInRepository(User\Device::class, [
            'id' => $mainId.'_a2476e7a-91f5-11ea-bb37-0242ac130002',
            'user' => [
                'email' => self::MAIN_USER_EMAIL
            ],
            'token' => '111620f9-848a-49be-a7a2-8fdaa01856e4',
            'locale' => 'RU',
            'timeZone' => 'Europe/Moscow',
        ]);

        //Push device with exists token from another account
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPOST('/v1/device', json_encode([
            'deviceId' => 'a2476e7a-91f5-11ea-bb37-0242ac130033',
            'locale' => 'RU',
            'pushToken' => '111620f9-848a-49be-a7a2-8fdaa01856e4',
            'timeZone' => 'Europe/Moscow'
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $devices = $I->grabEntitiesFromRepository(User\Device::class, ['user' => ['email' => self::ALICE_USER_EMAIL]]);
        $I->assertCount(1, $devices);
        $I->seeInRepository(User\Device::class, [
            'id' => $aliceId.'_a2476e7a-91f5-11ea-bb37-0242ac130033',
            'user' => [
                'email' => self::ALICE_USER_EMAIL
            ],
            'token' => '111620f9-848a-49be-a7a2-8fdaa01856e4',
            'locale' => 'RU',
            'timeZone' => 'Europe/Moscow',
        ]);
        //Check device has been removed from another user and created for user alice
        $I->dontSeeInRepository(User\Device::class, ['id' => $mainId.'_a2476e7a-91f5-11ea-bb37-0242ac130002']);
    }

    public function newDevicesReactNativeCest(ApiTester $I)
    {
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPOST('/v1/device', json_encode([
            'deviceId' => '82847240-6c0c-4247-b618-bf61bbe18a72',
            'locale' => 'RU',
            'pushToken' => '98b9b0fe-d79b-4e3c-88ac-61094179dbde',
            'type' => 'android-react'
        ]));
        $I->sendPOST('/v1/device', json_encode([
            'deviceId' => 'f49559b1-3f5b-4066-aaa0-c1ec65f16e50',
            'locale' => 'RU',
            'pushToken' => '19551d3a-a88b-45d9-a7bd-bf1ed900bb6f',
            'type' => 'ios-react'
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(User\Device::class, [
            'id' => $aliceId.'_f49559b1-3f5b-4066-aaa0-c1ec65f16e50',
            'user' => [
                'email' => self::ALICE_USER_EMAIL
            ],
            'type' => User\Device::TYPE_IOS_REACT
        ]);
        $I->seeInRepository(User\Device::class, [
            'id' => $aliceId.'_82847240-6c0c-4247-b618-bf61bbe18a72',
            'user' => [
                'email' => self::ALICE_USER_EMAIL
            ],
            'type' => User\Device::TYPE_ANDROID_REACT
        ]);
    }

    public function deleteDeviceOnLogount(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $deviceBody = json_encode([
            'deviceId' => 'a2476e7a-91f5-11ea-bb37-0242ac130002',
            'locale' => 'RU',
            'pushToken' => '070797d2-252a-4fbf-ae50-f44bcd31b6ed',
            'timeZone' => 'Europe/Moscow'
        ]);

        $I->sendPOST('/v1/device', $deviceBody);
        $I->seeResponseCodeIs(HttpCode::OK);

        $devices = $I->grabEntitiesFromRepository(User\Device::class, ['user' => ['email' => self::MAIN_USER_EMAIL]]);
        $I->assertCount(1, $devices);

        $I->sendPOST('/v1/account/logout', $deviceBody);
        $I->seeResponseCodeIs(HttpCode::OK);

        $devices = $I->grabEntitiesFromRepository(User\Device::class, ['user' => ['email' => self::MAIN_USER_EMAIL]]);
        $I->assertCount(0, $devices);
    }

    public function testEmptyPushTokenDevice(ApiTester $I)
    {
        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $deviceBody = json_encode([
            'deviceId' => 'a2476e7a-91f5-11ea-bb37-0242ac130002',
            'locale' => 'RU',
            'model' => 'iPhone 12 Pro Max',
            'timeZone' => 'Europe/Moscow',
            'type' => 'ios-react',
        ]);
        $I->sendPOST('/v1/device', $deviceBody);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInRepository(User\Device::class, [
            'id' => $mainId.'_a2476e7a-91f5-11ea-bb37-0242ac130002',
            'user' => ['email' => self::MAIN_USER_EMAIL],
            'type' => User\Device::TYPE_IOS_REACT,
            'model' => 'iPhone 12 Pro Max',
            'token' => null,
        ]);

        //Repeat previous request as same user
        $deviceBody = json_encode([
            'deviceId' => 'a2476e7a-91f5-11ea-bb37-0242ac130002',
            'locale' => 'RU',
            'model' => 'iPhone 12 Pro Max',
            'timeZone' => 'Europe/Moscow',
            'type' => 'ios-react',
        ]);
        $I->sendPOST('/v1/device', $deviceBody);
        $I->seeResponseCodeIs(HttpCode::OK);

        //Repeat previous request as another user (authorize from another device)
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $deviceBody = json_encode([
            'deviceId' => 'a2476e7a-91f5-11ea-bb37-0242ac130002',
            'locale' => 'RU',
            'model' => 'iPhone 12 Pro Max',
            'timeZone' => 'Europe/Moscow',
            'type' => 'ios-react',
        ]);
        $I->sendPOST('/v1/device', $deviceBody);
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
