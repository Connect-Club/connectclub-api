<?php

namespace App\Tests\V2;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Controller\ErrorCode;
use App\DataFixtures\AccessTokenFixture;
use App\Entity\Activity\NewUserFromWaitingListActivity;
use App\Entity\Follow\Follow;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\Invite\Invite;
use App\Entity\Role;
use App\Entity\User;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Message\SendNotificationMessage;
use App\Message\SendSmsMessage;
use App\Message\SyncWithIntercomMessage;
use App\Message\UploadUserToElasticsearchMessage;
use App\Service\LanguageManager;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\V2\User\UserCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use MaxMind\Db\Reader;
use Mockery;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AccountCest extends BaseCest
{
    const JSON_RESPONSE_ACCOUNT_FORMAT = [
        'id' => 'string',
        'username' => 'string|null',
        'name' => 'string',
        'surname' => 'string',
        'avatar' => 'string|null',
        'state' => 'string',
        'about' => 'string',
        'joinedBy' => 'array|null',
        'interests' => 'array',
        'skipNotificationUntil' => 'int|null',
        'badges' => 'array',
        'language' => 'array|null',
        'shortBio' => 'string|null',
        'longBio' => 'string|null',
        'twitter' => 'string|null',
        'instagram' => 'string|null',
        'linkedin' => 'string|null',
        'wallet' => 'string|null',
        'isSuperCreator' => 'boolean',
        'skills' => 'array',
        'goals' => 'array',
        'industries' => 'array',
        'languages' => 'array',
        'joinedByClubRole' => 'string|null',
        'enableDeleteWallet' => 'boolean',
        'createdAt' => 'integer',
    ];

    public function testGetAccount(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $interestGroup = new InterestGroup('Group');
                $manager->persist($interestGroup);

                $languageInterestEnglish = new User\Language('English', 'EN');
                $languageInterestEnglish->isDefaultInterestForRegions = true;
                $manager->persist($languageInterestEnglish);

                $languageInterestRussia = new User\Language('Russia', 'RU');
                $languageInterestRussia->automaticChooseForRegionCodes = ['RU', 'UA', 'BY'];
                $manager->persist($languageInterestRussia);

                $alice = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $alice->addRole(Role::ROLE_SUPERCREATOR);

                $manager->flush();
            }
        });

        foreach (['RU', 'UA', 'BY'] as $regionCode) {
            $readerMock = Mockery::mock(Reader::class);
            $readerMock->shouldReceive('get')->with('127.0.0.1')->andReturn([
                'country' => [
                    'iso_code' => $regionCode
                ]
            ])->once();
            $I->mockService(Reader::class, $readerMock);

            $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
            $I->sendGet('/v2/account');
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseMatchesJsonTypeStrict(self::JSON_RESPONSE_ACCOUNT_FORMAT);

            $I->seeResponseContainsJson([
                'language' => [
                    'name' => 'Russia'
                ],
                'isSuperCreator' => false,
            ]);

            $I->loadFixtures(new class extends Fixture {
                public function load(ObjectManager $manager)
                {
                    $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                    $main->nativeLanguages->clear();

                    $manager->flush();
                }
            }, true);
        }

        $readerMock = Mockery::mock(Reader::class);
        $readerMock->shouldReceive('get')->with('127.0.0.1')->andReturn([
            'country' => [
                'iso_code' => 'GE'
            ]
        ])->once();
        $I->mockService(Reader::class, $readerMock);

        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendGet('/v2/account');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseMatchesJsonTypeStrict(self::JSON_RESPONSE_ACCOUNT_FORMAT);

        $I->seeResponseContainsJson([
            'response' => [
                'language' => [
                    'name' => 'English',
                ],
                'isSuperCreator' => true,
            ]
        ]);

        $this->mockElasticsearchClient($I);

        /** @var User\Language $languageRu */
        $languageRu = $I->grabEntityFromRepository(User\Language::class, ['name' => 'Russia']);
        $I->sendPatch('/v2/account', json_encode(['languageId' => $languageRu->id]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'language' => [
                    'name' => 'Russia'
                ]
            ]
        ]);

        $I->sendGet('/v2/account');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'language' => [
                    'name' => 'Russia'
                ]
            ]
        ]);
    }

    public function testEmptyInterests(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $interestGroup = new InterestGroup('Group');
                $manager->persist($interestGroup);
                $interestA = new Interest($interestGroup, 'InterestA', 0, false);
                $manager->persist($interestA);
                $interestB = new Interest($interestGroup, 'InterestB', 0, false);
                $manager->persist($interestB);
                $interestC = new Interest($interestGroup, 'InterestC', 0, false);
                $interestC->globalSort = 1;
                $manager->persist($interestC);

                $manager->flush();
            }
        });

        $interestA = $I->grabEntityFromRepository(Interest::class, ['name' => 'InterestA'])->id;
        $interestB = $I->grabEntityFromRepository(Interest::class, ['name' => 'InterestB'])->id;
        $interestC = $I->grabEntityFromRepository(Interest::class, ['name' => 'InterestC'])->id;

        $this->mockElasticsearchClient($I);

        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPatch('/v2/account', json_encode(['interests' => [
            ['id' => $interestA],
            ['id' => $interestB],
            ['id' => $interestC]
        ]]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'interests' => [
                0 => ['name' => 'InterestA'],
                1 => ['name' => 'InterestB'],
                2 => ['name' => 'InterestC'],
            ],
        ]);

        $I->sendPatch('/v2/account', json_encode(['interests' => []]));
        $interests = $I->grabDataFromResponseByJsonPath('$.response.interests')[0];
        $I->assertCount(0, $interests);
    }

    public function testPatchProfileReserveUsernameCest(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $this->mockElasticsearchClient($I);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPatch('/v2/account', json_encode(['username' => '--deva_nb-oo.t-wo']));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPatch('/v2/account', json_encode(['username' => 'devanboo']));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v2/account');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['username' => 'devanboo']);
        $I->seeResponseMatchesJsonTypeStrict(self::JSON_RESPONSE_ACCOUNT_FORMAT);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPatch('/v2/account', json_encode(['username' => 'devanboo']));
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_USER_USERNAME_ALREADY_EXISTS]]);
    }

    public function testValidationPatchAccount(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPatch('/v2/account', json_encode([
            'name' => '',
            'surname' => '',
            'about' => ''
        ]));
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeResponseContainsJson([
            'errors' => ['name:cannot_be_empty','surname:cannot_be_empty'],
        ]);
    }

    public function testSuccessPatchAccount(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $this->mockElasticsearchClient($I);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPatch('/v2/account', json_encode([
            'name' => 'Danil ',
            'surname' => 'Andreyev ',
            'about' => 'Backend programmer from Connect.Club',
            'username' => 'devnaboo',
            'linkedin' => 'danil_andreev_linkedin',
            'instagram' => 'danil_andreev_instagram',
            'twitter' => 'danil_andreev_twitter',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v2/account');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'name' => 'Danil',
                'surname' => 'Andreyev',
                'about' => 'Backend programmer from Connect.Club',
                'username' => 'devnaboo',
                'linkedin' => 'danil_andreev_linkedin',
                'instagram' => 'danil_andreev_instagram',
                'twitter' => 'danil_andreev_twitter',
            ]
        ]);
        $I->seeResponseMatchesJsonTypeStrict(self::JSON_RESPONSE_ACCOUNT_FORMAT);
    }

    public function testVerify(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $util = PhoneNumberUtil::getInstance();

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $contact = new User\PhoneContact(
                    $alice,
                    '+79636417686',
                    $util->parse('+79636417686'),
                    'Contact from Alice'
                );
                $manager->persist($contact);

                $contact = new User\PhoneContact(
                    $mike,
                    '+79636417686',
                    $util->parse('+79636417686'),
                    'Contact from Mike'
                );
                $manager->persist($contact);

                $manager->persist(new User\Device(
                    Uuid::uuid4(),
                    $alice,
                    User\Device::TYPE_IOS_REACT,
                    'token',
                    null,
                    'RU'
                ));

                $main->state = User::STATE_INVITED;
                $main->name = 'Maxim';
                $main->surname = 'Ivanov';
                $main->phone = $util->parse('+79636417686');

                $manager->persist($main);
                $manager->flush();
            }
        }, true);

        $user = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertNotEquals(User::STATE_VERIFIED, $user->state);

        $this->mockElasticsearchClient($I, true);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/account/verify');
        $I->seeResponseCodeIs(HttpCode::OK);

        $user = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertEquals(User::STATE_VERIFIED, $user->state);
    }

    public function testSkipNotificationUntil(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $this->mockElasticsearchClient($I, true);

        $skipNotificationUntil = time() + 3600;

        /** @var User $main */
        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);

        $I->assertNull($main->skipNotificationUntil);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPatch('/v2/account', json_encode(['skipNotificationUntil' => $skipNotificationUntil]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->refreshEntities($main);
        $I->assertEquals($skipNotificationUntil, $main->skipNotificationUntil);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPatch('/v2/account', json_encode(['skipNotificationUntil' => 0]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        $I->assertNull($main->skipNotificationUntil);
    }

    private function mockElasticsearchClient(ApiTester $I, bool $userRegisteredAmplitudeStatistics = false)
    {
        $busMock = Mockery::mock(MessageBusInterface::class);
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::type(UploadUserToElasticsearchMessage::class))
            ->andReturn(new Envelope(Mockery::mock(UploadUserToElasticsearchMessage::class)));
        $busMock
            ->shouldReceive('dispatch')
            ->with(Mockery::type(SyncWithIntercomMessage::class))
            ->andReturn(new Envelope(Mockery::mock(SyncWithIntercomMessage::class)));

        if ($userRegisteredAmplitudeStatistics) {
            $busMock
                ->shouldReceive('dispatch')
                ->with(Mockery::on(fn($message) => $message instanceof AmplitudeEventStatisticsMessage))
                ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        }

        $I->mockService(MessageBusInterface::class, $busMock);
    }
}
