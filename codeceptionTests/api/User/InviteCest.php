<?php

namespace App\Tests\User;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Client\VonageSMSClient;
use App\Controller\ErrorCode;
use App\Entity\Activity\NewUserFromWaitingListActivity;
use App\Entity\Activity\NewUserRegisteredByInviteCodeActivity;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\SendSmsMessage;
use App\Message\SyncWithIntercomMessage;
use App\Message\UploadUserToElasticsearchMessage;
use App\OAuth2\Extension\PhoneNumberGrantExtension;
use App\Service\JwtToken;
use App\Service\MetamaskManager;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\V2\AccountCest;
use App\Tests\V2\User\UserCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Vonage\Verify\Client;
use Vonage\Verify\Verification;
use const UPLOAD_ERR_OK;

class InviteCest extends BaseCest
{
    public function test(ApiTester $I)
    {
        $jwtTokenServiceMock = \Mockery::mock(JwtToken::class);
        $jwtTokenServiceMock->shouldReceive('getJWTClaim')->andReturn(Uuid::uuid4()->toString());
        $I->mockService(JwtToken::class, $jwtTokenServiceMock);

        $I->sendPOST('/v1/sms/verification', json_encode(['phone' => 'asd']));
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeResponseContainsJson(['errors' => ['phone:not_valid_mobile_phone_number']]);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::on(fn($message) => $message instanceof SendSmsMessage), Mockery::any())
            ->andReturn(new Envelope(Mockery::mock(SendSmsMessage::class)))
            ->once();
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::on(fn($message) => $message instanceof AmplitudeEventStatisticsMessage))
            ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $busMock);

        $lockMock = Mockery::mock(SharedLockInterface::class);
        $lockMock->shouldReceive('acquire')->andReturn(true);
        $lockFactoryMock = Mockery::mock(LockFactory::class);
        $lockFactoryMock->shouldReceive('createLock')->andReturn($lockMock);
        $I->mockService(LockFactory::class, $lockFactoryMock);

        $I->sendPOST('/v1/sms/verification', json_encode(['phone' => '+79636417686']));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendGET('/oauth/v2/token?'.http_build_query([
            'grant_type' => 'https://connect.club/sms',
            'phone' => 'asdasd',
            'code' => '0000',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['error' => PhoneNumberGrantExtension::ERROR_INCORRECT_PHONE_NUMBER]);

        $I->sendGET('/oauth/v2/token?'.http_build_query([
            'grant_type' => 'https://connect.club/sms',
            'phone' => '',
            'code' => '0000',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['error' => PhoneNumberGrantExtension::ERROR_PHONE_NOT_FOUND]);

        $I->sendGET('/oauth/v2/token?'.http_build_query([
            'grant_type' => 'https://connect.club/sms',
            'phone' => 'asdasd',
            'code' => '',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['error' => PhoneNumberGrantExtension::ERROR_CODE_NOT_FOUND]);

        $I->sendGET('/oauth/v2/token?'.http_build_query([
            'grant_type' => 'https://connect.club/sms',
            'phone' => '+79636417686',
            'code' => '0000',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['error' => PhoneNumberGrantExtension::ERROR_INCORRECT_CODE]);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $manager->persist(new User\SmsVerification('+79636417686', 'vonage_request_id'));
                $manager->flush();
            }
        }, true);

        $verification = Mockery::mock(Verification::class);
        $verification->shouldReceive('toArray')->andReturn([]);
        $vonageVerifyClient = Mockery::mock(Client::class);
        $vonageVerifyClient->shouldReceive('check')->with(Mockery::any(), '1212')->andReturn($verification);
        $vonageSmsClientMock = Mockery::mock(VonageSMSClient::class);
        $vonageSmsClientMock->shouldReceive('verify')->andReturn($vonageVerifyClient);
        $I->mockService(VonageSMSClient::class, $vonageSmsClientMock);

        $I->sendGET('/oauth/v2/token?'.http_build_query([
            'grant_type' => 'https://connect.club/sms',
            'phone' => '+79636417686',
            'code' => '1212',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(User::class, ['phone' => '+79636417686']);
        $user = $I->grabEntityFromRepository(User::class, ['phone' => '+79636417686']);
        $I->assertEquals(User::STATE_NOT_INVITED, $user->state);

        $I->amBearerAuthenticated($I->grabDataFromResponseByJsonPath('$.access_token')[0]);
        $I->sendGet('/v2/account');
        $I->seeResponseCodeIs(HttpCode::OK);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::type(UploadUserToElasticsearchMessage::class))
            ->andReturn(new Envelope(Mockery::mock(UploadUserToElasticsearchMessage::class)));
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::type(SyncWithIntercomMessage::class))
            ->andReturn(new Envelope(Mockery::mock(SyncWithIntercomMessage::class)));
        $I->mockService(MessageBusInterface::class, $busMock);

        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->sendPatch('/v2/account', json_encode(['username' => 'devanboo']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(User::class, ['phone' => '+79636417686', 'username' => 'devanboo']);

        $I->sendPatch('/v2/account', json_encode(['name' => 'Danil', 'surname' => 'Andreyev']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(User::class, [
            'phone' => '+79636417686',
            'username' => 'devanboo',
            'name' => 'Danil',
            'surname' => 'Andreyev'
        ]);

        $I->sendPOST('/v1/upload/user-photo', [], [
            'photo' => [
                'name' => 'video_room_background.png',
                'type' => 'image/png',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize(codecept_data_dir('video_room_background.png')),
                'tmp_name' => codecept_data_dir('video_room_background.png')
            ]
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $photoId = $I->grabDataFromResponseByJsonPath('$.response.id')[0];

        $I->sendPatch('/v2/account', json_encode(['avatar' => $photoId]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v2/account');
        $I->seeResponseContainsJson([
            'response' => [
                'username' => 'devanboo',
                'name' => 'Danil',
                'surname' => 'Andreyev',
                'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/.png',
                'state' => User::STATE_NOT_INVITED
            ]
        ]);
        $I->seeResponseMatchesJsonTypeStrict(AccountCest::JSON_RESPONSE_ACCOUNT_FORMAT);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPOST('/v1/invite', json_encode(['phone' => 'asdsad']));
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeResponseContainsJson(['errors' => ['phone:not_valid_mobile_phone_number']]);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::on(fn($message) => $message instanceof AmplitudeEventStatisticsMessage))
            ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $busMock);

        $I->sendPOST('/v1/invite', json_encode(['phone' => '+79636417686']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeInRepository(Invite::class, [
            'phoneNumber' => '+79636417686',
            'author' => [
                'email' => self::MAIN_USER_EMAIL,
            ]
        ]);
        $user = $I->grabEntityFromRepository(User::class, ['phone' => '+79636417686']);
        $I->assertEquals(User::STATE_INVITED, $user->state);

        $I->sendPOST('/v1/invite', json_encode(['phone' => '+79636417686']));
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_ERROR_INVITE_ALREADY_EXISTS]]);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::on(fn($message) => $message instanceof SendSmsMessage), Mockery::any())
            ->andReturn(new Envelope(Mockery::mock(SendSmsMessage::class)))
            ->once();
        $I->mockService(MessageBusInterface::class, $busMock);

        $I->sendPOST('/v1/sms/verification', json_encode(['phone' => '+79636417686']));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $verification = Mockery::mock(Verification::class);
        $verification->shouldReceive('toArray')->andReturn([]);
        $vonageVerifyClient = Mockery::mock(Client::class);
        $vonageVerifyClient->shouldReceive('check')->with(Mockery::any(), '4444')->andReturn($verification);
        $vonageSmsClientMock = Mockery::mock(VonageSMSClient::class);
        $vonageSmsClientMock->shouldReceive('verify')->andReturn($vonageVerifyClient);
        $I->mockService(VonageSMSClient::class, $vonageSmsClientMock);

        $I->sendGET('/oauth/v2/token?'.http_build_query([
            'grant_type' => 'https://connect.club/sms',
            'phone' => '+79636417686',
            'code' => '4444',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->amBearerAuthenticated($I->grabDataFromResponseByJsonPath('$.access_token')[0]);
        $I->sendGet('/v2/account');
        $I->seeResponseCodeIs(HttpCode::OK);

        $accountJsonFormat = AccountCest::JSON_RESPONSE_ACCOUNT_FORMAT;
        $accountJsonFormat['joinedBy'] = UserCest::USER_SLIM_FORMAT_RESPONSE_JSON;
        $I->seeResponseMatchesJsonTypeStrict($accountJsonFormat);
        $I->canSeeResponseContainsJson([
            'response' => [
                'joinedBy' => [
                    'name' => 'main_user_name',
                    'surname' => 'main_user_surname',
                    'displayName' => 'main_user_name main_user_surname',
                ]
            ]
        ]);
    }

    public function testDecrement(ApiTester $I)
    {
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::on(fn($message) => $message instanceof AmplitudeEventStatisticsMessage))
            ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $busMock);

        /** @var User $main */
        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);

        $I->assertEquals(20, $main->freeInvites);
        $I->sendPost('/v1/invite', json_encode(['phone' => '+79636416344']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->refreshEntities($main);
        $I->assertEquals(19, $main->freeInvites);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $manager->persist(new NewUserFromWaitingListActivity(
                    PhoneNumberUtil::getInstance()->parse('+79636414444'),
                    $main,
                    $main
                ));

                $manager->flush();
            }
        }, true);

        $I->sendPost('/v1/invite', json_encode([
            'phone' => '+79636414444',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        /** @var User $main */
        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEquals(18, $main->freeInvites);
    }

    public function testNoFreeInvites(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->freeInvites = 0;

                $manager->persist($main);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/invite', json_encode(['phone' => '+79636416344']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES]]);
    }

    public function testInviteByUserId(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository('App:User');

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->inviteCode = '3eac1e58-fac7-4cf7-b7dd-64673d538e5d';

                $manager->persist($main);
                $manager->flush();
            }
        }, true);

        $metamaskMock = Mockery::mock(MetamaskManager::class);
        $metamaskMock->shouldReceive('checkMetamaskWallet')->andReturn(true);
        $I->mockService(MetamaskManager::class, $metamaskMock);

        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock->shouldReceive('dispatch')
                ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $busMock);

        $I->sendPost('/v1/user/wallet/auth-signature', json_encode(['deviceId' => 'dddd']));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGET('/oauth/v2/token?'.http_build_query([
            'grant_type' => 'https://connect.club/metamask',
            'client_id' => self::OAUTH_CLIENT_ID,
            'client_secret' => self::OAUTH_CLIENT_SECRET,
            'inviteCode' => '3eac1e58-fac7-4cf7-b7dd-64673d538e5d',
            'text' => 'Connect my wallet with Connect.Club account. Nonce: eb96c208-2cdd-4c52-9264-147ed7b9a833',
            'address' => '0xE0985715d015E4C679dd7d1df1F71CbB9a4B4123',
            //phpcs:ignore
            'signature' => '0xaf34c3804defeac60fde18733a254b06dc452e33f8c2fff6610a041e014808245615c9bf1bbec03e85a1d91c9e5e259b0c93c5b53d5e0bd2f1618a4eba6b09c71c',
            'device_id' => 'dddd'
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->amBearerAuthenticated($I->grabDataFromResponseByJsonPath('$.access_token')[0]);
        $I->sendPatch('/v2/account/'.User::STATE_WAITING_LIST.'/state');
        $I->seeResponseCodeIs(HttpCode::OK);

        $userId = $I->grabFromRepository(User::class, 'id', [
            'wallet' => mb_strtolower('0xE0985715d015E4C679dd7d1df1F71CbB9a4B4123')
        ]);

        $activity = $I->grabEntityFromRepository(NewUserRegisteredByInviteCodeActivity::class, [
            'user' => [
                'email' => self::MAIN_USER_EMAIL
            ]
        ]);
        $I->assertEquals($userId, $activity->nestedUsers->first()->id);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/invite/'.$userId);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInRepository(NewUserRegisteredByInviteCodeActivity::class, ['id' => $activity->id->toString()]);
    }
}
