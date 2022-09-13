<?php

namespace App\Tests\Auth;

use App\Client\VonageSMSClient;
use App\Entity\User\SmsVerification;
use App\Service\JwtToken;
use App\Service\SMS\TestPhoneNumberSmsProvider;
use App\Service\SMS\TwilioSmsProvider;
use App\Service\SMS\VonageSmsProvider;
use App\Service\TwilioEndpointManager;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Mockery;
use Ramsey\Uuid\Uuid;
use stdClass;
use Twilio\Rest\Verify\V2\Service\VerificationInstance;
use Vonage\Verify\Verification;

class SmsCest extends BaseCest
{
    public function testVerification(ApiTester $I)
    {
        $jwtTokenServiceMock = \Mockery::mock(JwtToken::class);
        $jwtTokenServiceMock->shouldReceive('getJWTClaim')->andReturn(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString()
        );
        $I->mockService(JwtToken::class, $jwtTokenServiceMock);

        $_ENV['STAGE'] = 0;
        $_ENV['DISABLE_SMS_IP_VERIFICATION'] = 1;

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        //Test phone number with +7907

        $I->sendPost('/v1/sms/verification', json_encode(['phone' => '+79076417683']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $lastSmsVerification = $I->grabEntityFromRepository(SmsVerification::class, ['phoneNumber' => '+79076417683']);
        $I->assertEquals(TestPhoneNumberSmsProvider::CODE, $lastSmsVerification->providerCode);

        $twilioEndpointManagerMock = Mockery::mock(TwilioEndpointManager::class);
        $verificationInstance = Mockery::mock(VerificationInstance::class);
        $verificationInstance->sid = 'twilioSid';
        $twilioEndpointManagerMock->shouldReceive('sendVerificationCode')->once()->andReturn($verificationInstance);
        $I->mockService(TwilioEndpointManager::class, $twilioEndpointManagerMock);

        $I->sendPost('/v1/sms/verification', json_encode(['phone' => '+79636417683']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->grabEntityFromRepository(SmsVerification::class, [
            'phoneNumber' => '+79636417683',
            'providerCode' => TwilioSmsProvider::CODE,
            'remoteId' => 'twilioSid'
        ]);

        /** @var SmsVerification $lastSmsVerification */
        $lastSmsVerification = $I->grabEntityFromRepository(SmsVerification::class, ['phoneNumber' => '+79636417683']);
        $lastSmsVerification->cancelledAt = time();
        $I->haveInRepository($lastSmsVerification);

        //Test phone number with priority vonage
        $vonageSmsClientMock = Mockery::mock(VonageSMSClient::class);
        $verification = Mockery::mock(Verification::class);
        $verification->shouldReceive('getRequestId')->andReturn('remoteIdFromVonage');
        $vonageSmsClientMock->shouldReceive('start')->once()->andReturn($verification);
        $I->mockService(VonageSMSClient::class, $vonageSmsClientMock);
        $I->sendPost('/v1/sms/verification', json_encode(['phone' => '+79636417683']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->grabEntityFromRepository(SmsVerification::class, [
            'phoneNumber' => '+79636417683',
            'providerCode' => VonageSmsProvider::CODE,
            'remoteId' => 'remoteIdFromVonage'
        ]);
    }
}
