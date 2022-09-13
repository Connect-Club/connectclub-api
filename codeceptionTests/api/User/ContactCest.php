<?php

namespace App\Tests\User;

use App\DataFixtures\AccessTokenFixture;
use App\Entity\User\PhoneContactNumber;
use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Entity\User\PhoneContact;
use App\Message\SendNotificationMessage;
use App\Message\UploadPhoneContactsMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Mockery;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ContactCest extends BaseCest
{
    public function testBiggestRequest(ApiTester $I)
    {
        $newContacts = [];
        for ($i = 100; $i < 400; $i++) {
            $newContacts[] = [
                'phoneNumber' => '+79636'.$i.'683',
                'fullName' => 'Danil Andreyev '.$i
            ];
        }

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => ['email' => self::MAIN_USER_EMAIL,],
        ]);
        $I->assertCount(300, $phoneContacts);
    }

    public function testEmptyRequest(ApiTester $I)
    {
        $newContacts = [];

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => ['email' => self::MAIN_USER_EMAIL,],
        ]);
        $I->assertCount(0, $phoneContacts);
    }

    public function uploadCest(ApiTester $I)
    {
        $newContacts = [
            [
                'phoneNumber' => '+79636417683',
                'fullName' => 'Danil Andreyev'
            ],
            [
                'phoneNumber' => '+79636417683',
                'fullName' => 'Danil Andreyev'
            ],
            [
                'phoneNumber' => '+74954773316',
                'fullName' => 'Golangutang Fuppy'
            ],
            [
                'phoneNumber' => '+1 214 8922 855',
                'fullName' => 'Twilio Director Miphector Duplicate 1'
            ],
            [
                'phoneNumber' => '+1 214 892 28 55',
                'fullName' => 'Twilio Director Miphector Duplicate 2'
            ],
            [
                'phoneNumber' => '+12148922855',
                'fullName' => 'Twilio Director Miphector'
            ],
        ];

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => ['email' => self::MAIN_USER_EMAIL,],
        ]);
        $I->assertCount(2, $phoneContacts);

        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+79636417683',
            'fullName' => 'Danil Andreyev',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+12148922855',
            'fullName' => 'Twilio Director Miphector',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);

        //Repeat request with same phone numbers
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => [
                'email' => self::MAIN_USER_EMAIL,
            ],
        ]);
        $I->assertCount(2, $phoneContacts);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+79636417683',
            'fullName' => 'Danil Andreyev',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+12148922855',
            'fullName' => 'Twilio Director Miphector',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => [
                'email' => self::BOB_USER_EMAIL,
            ],
        ]);
        $I->assertCount(2, $phoneContacts);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+79636417683',
            'fullName' => 'Danil Andreyev',
            'owner' => ['email' => self::BOB_USER_EMAIL,]
        ]);

        $twilioDirectorContact = $I->grabEntityFromRepository(PhoneContact::class, [
            'phoneNumber' => '+12148922855',
            'fullName' => 'Twilio Director Miphector',
            'owner' => ['email' => self::BOB_USER_EMAIL,]
        ]);

        //Test renaming exists contact
        $I->assertEquals('Twilio Director Miphector', $twilioDirectorContact->fullName);
        $newContacts = [
            [
                'phoneNumber' => '+12148922855',
                'fullName' => 'Twilio Director Renamed'
            ],
        ];
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $twilioDirectorContacts = $I->grabEntitiesFromRepository(
            PhoneContact::class,
            ['phoneNumber' => '+12148922855', 'owner' => ['email' => self::BOB_USER_EMAIL]]
        );
        $I->assertCount(1, $twilioDirectorContacts);
        $twilioDirectorContact = $twilioDirectorContacts[0];
        $I->assertEquals('Twilio Director Renamed', $twilioDirectorContact->fullName);

        $I->sendGet('/v1/contact-phone');
        $I->seeResponseCodeIs(HttpCode::OK);

        //Contacts not provided in last request must be removed
        //Expects only one contact (Twilio Director Renamed)
        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => ['email' => self::BOB_USER_EMAIL],
        ]);
        $I->assertCount(1, $phoneContacts);
    }

    public function uploadCestNewVersion(ApiTester $I)
    {
        $newContacts = [
            [
                'phoneNumbers' => ['+79636417683'],
                'fullName' => 'Danil Andreyev'
            ],
            [
                'phoneNumbers' => ['+79636417683', '+79637417683', '+79638417683'],
                'fullName' => 'Danil Andreyev'
            ],
            [
                'phoneNumbers' => ['+74954773316', '+74954773315', '+74954773314'],
                'fullName' => 'Golangutang Fuppy'
            ],
            [
                'phoneNumbers' => ['+1 214 8922 855'],
                'fullName' => 'Twilio Director Miphector Duplicate 1'
            ],
            [
                'phoneNumbers' => ['+1 214 892 28 55'],
                'fullName' => 'Twilio Director Miphector Duplicate 2'
            ],
            [
                'phoneNumbers' => ['+12148922855', '+12148922856', '+12148922857'],
                'fullName' => 'Twilio Director Miphector'
            ],
            [
                'phoneNumbers' => ['+12148922851'],
                'fullName' => 'Vitya',
                'thumbnail' => '/var/mobile/Containers/Data/Application/2FF9C85D-900B-4B0B-B7FF-A2636299231E'
                    .'/Library/Caches/rncontacts_89C2A7DC-3881-4B86-B42A-1825ADF1FA48.png'
            ],
        ];

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('/v1/contact-phone');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'phones' => [
                            '+79636417683',
                            '+79637417683',
                            '+79638417683',
                        ],
                        'displayName' => 'Danil Andreyev',
                        'status' => 'new',
                        'countInAnotherUsers' => 0,
                        'thumbnail' => null,
                    ],
                    [
                        'phones' => [
                            '+12148922855',
                            '+12148922856',
                            '+12148922857',
                        ],
                        'displayName' => 'Twilio Director Miphector',
                        'status' => 'new',
                        'countInAnotherUsers' => 0,
                        'thumbnail' => null,
                    ],
                    [
                        'phones' => [
                            '+12148922851',
                        ],
                        'displayName' => 'Vitya',
                        'status' => 'new',
                        'countInAnotherUsers' => 0,
                        'thumbnail' => '/var/mobile/Containers/Data/Application/2FF9C85D-900B-4B0B-B7FF-A2636299231E'
                            .'/Library/Caches/rncontacts_89C2A7DC-3881-4B86-B42A-1825ADF1FA48.png'
                    ]
                ],
                'lastValue' => null
            ]
        ]);

        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => ['email' => self::MAIN_USER_EMAIL,],
        ]);
        $I->assertCount(3, $phoneContacts);

        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+79636417683',
            'fullName' => 'Danil Andreyev',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);
        $I->seeInRepository(PhoneContactNumber::class, [
            'phoneNumber' => '+79636417683',
            'phoneContact' => [
                'phoneNumber' => '+79636417683',
                'fullName' => 'Danil Andreyev',
                'owner' => ['email' => self::MAIN_USER_EMAIL,]
            ]
        ]);
        $I->seeInRepository(PhoneContactNumber::class, [
            'phoneNumber' => '+79637417683',
            'phoneContact' => [
                'phoneNumber' => '+79636417683',
                'fullName' => 'Danil Andreyev',
                'owner' => ['email' => self::MAIN_USER_EMAIL,]
            ]
        ]);
        $I->seeInRepository(PhoneContactNumber::class, [
            'phoneNumber' => '+79638417683',
            'phoneContact' => [
                'phoneNumber' => '+79636417683',
                'fullName' => 'Danil Andreyev',
                'owner' => ['email' => self::MAIN_USER_EMAIL,]
            ]
        ]);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+12148922855',
            'fullName' => 'Twilio Director Miphector',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);
        $I->seeInRepository(PhoneContactNumber::class, [
            'phoneNumber' => '+12148922855',
            'phoneContact' => [
                'phoneNumber' => '+12148922855',
                'fullName' => 'Twilio Director Miphector',
                'owner' => ['email' => self::MAIN_USER_EMAIL,]
            ]
        ]);

        //Repeat request with same phone numbers
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => [
                'email' => self::MAIN_USER_EMAIL,
            ],
        ]);
        $I->assertCount(3, $phoneContacts);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+79636417683',
            'fullName' => 'Danil Andreyev',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+12148922855',
            'fullName' => 'Twilio Director Miphector',
            'owner' => ['email' => self::MAIN_USER_EMAIL,]
        ]);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => [
                'email' => self::BOB_USER_EMAIL,
            ],
        ]);
        $I->assertCount(3, $phoneContacts);
        $I->seeInRepository(PhoneContact::class, [
            'phoneNumber' => '+79636417683',
            'fullName' => 'Danil Andreyev',
            'owner' => ['email' => self::BOB_USER_EMAIL,]
        ]);

        $twilioDirectorContact = $I->grabEntityFromRepository(PhoneContact::class, [
            'phoneNumber' => '+12148922855',
            'fullName' => 'Twilio Director Miphector',
            'owner' => ['email' => self::BOB_USER_EMAIL,]
        ]);

        //Test renaming exists contact
        $I->assertEquals('Twilio Director Miphector', $twilioDirectorContact->fullName);
        $newContacts = [
            [
                'phoneNumber' => '+12148922855',
                'fullName' => 'Twilio Director Renamed'
            ],
        ];
        $I->sendPost('/v1/contact-phone', json_encode(['contacts' => $newContacts]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $twilioDirectorContact = $I->grabEntityFromRepository(
            PhoneContact::class,
            [
                'phoneNumber' => '+12148922855',
                'owner' => [
                    'email' => self::BOB_USER_EMAIL,
                ],
            ]
        );
        $I->assertEquals('Twilio Director Renamed', $twilioDirectorContact->fullName);

        $I->sendGet('/v1/contact-phone');
        $I->seeResponseCodeIs(HttpCode::OK);

        //Contacts not provided in last request must be removed
        //Expects only one contact (Twilio Director Renamed)
        $phoneContacts = $I->grabEntitiesFromRepository(PhoneContact::class, [
            'owner' => ['email' => self::BOB_USER_EMAIL],
        ]);
        $I->assertCount(1, $phoneContacts);
    }

    public function testListContacts(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository('App:User');

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $main->phone = PhoneNumberUtil::getInstance()->parse('+79636417383');
                $manager->persist($main);

                $katerinaPhoneNumber = '+79636417380';
                $viktorPhoneNumber = '+79636417381';
                $danielPhoneNumber = '+79636417383';

                $util = PhoneNumberUtil::getInstance();

                $manager->persist(new Invite($main, $util->parse($viktorPhoneNumber)));
                $manager->persist(new Invite($main, $util->parse('+79262250588')));

                $manager->persist(new PhoneContact(
                    $main,
                    $katerinaPhoneNumber,
                    $util->parse($katerinaPhoneNumber),
                    'Katya'
                ));

                $manager->persist(new PhoneContact(
                    $main,
                    $viktorPhoneNumber,
                    $util->parse($viktorPhoneNumber),
                    'Vitya Pishuk'
                ));

                $manager->persist(new PhoneContact(
                    $main,
                    $danielPhoneNumber,
                    $util->parse($danielPhoneNumber),
                    'Danya'
                ));

                $danyaSecondContact = new PhoneContact(
                    $main,
                    $danielPhoneNumber,
                    $util->parse('+79636417656'),
                    'Danya 2'
                );
                $danyaSecondContact->addAdditionalPhoneNumber(new PhoneContactNumber(
                    $danyaSecondContact,
                    $danielPhoneNumber,
                    $util->parse($danielPhoneNumber)
                ));
                $manager->persist($danyaSecondContact);

                $manager->persist(new PhoneContact(
                    $alice,
                    $danielPhoneNumber,
                    $util->parse($danielPhoneNumber),
                    'Danil'
                ));

                $manager->persist(new PhoneContact(
                    $bob,
                    $viktorPhoneNumber,
                    $util->parse($viktorPhoneNumber),
                    'Viktor'
                ));

                $manager->persist(new PhoneContact(
                    $alice,
                    $viktorPhoneNumber,
                    $util->parse($viktorPhoneNumber),
                    'Viktor from Alice'
                ));

                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/contact-phone');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'phone' => '+79636417383',
                        'displayName' => 'Danya',
                        'status' => 'invited',
                        'countInAnotherUsers' => 1,
                    ],
                    [
                        'phone' => '+79636417656',
                        'displayName' => 'Danya 2',
                        'status' => 'new',
                        'countInAnotherUsers' => 0,
                    ],
                    [
                        'phone' => '+79636417381',
                        'displayName' => 'Vitya Pishuk',
                        'status' => 'pending',
                        'countInAnotherUsers' => 2,
                    ],
                    [
                        'phone' => '+79636417380',
                        'displayName' => 'Katya',
                        'status' => 'new',
                        'countInAnotherUsers' => 0,
                    ],
                ]
            ]
        ]);

        $I->assertEquals('Danya', $I->grabDataFromResponseByJsonPath('$.response.items[0].displayName')[0]);
        $I->assertEquals('Danya 2', $I->grabDataFromResponseByJsonPath('$.response.items[1].displayName')[0]);
        $I->assertEquals('Katya', $I->grabDataFromResponseByJsonPath('$.response.items[2].displayName')[0]);
        $I->assertEquals('Vitya Pishuk', $I->grabDataFromResponseByJsonPath('$.response.items[3].displayName')[0]);

        $vityaResponse = [
            'phone' => '+79636417381',
            'displayName' => 'Vitya Pishuk',
            'status' => 'pending',
            'countInAnotherUsers' => 2,
        ];
        $I->sendGet('/v1/contact-phone?search=vit');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search=V');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search=Vitya');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search='.urlencode('as +79636417381'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search='.urlencode('+79636417381'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search='.urlencode('89636417381'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search='.urlencode('9636417381'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search=Pishuk');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->sendGet('/v1/contact-phone?search=pi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson($vityaResponse);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        $unknownResponse = [
            'response' => [
                'items' => [
                    0 => [
                        'phone' => '+375336443266',
                        'displayName' => '+375336443266',
                        'status' => 'unknown',
                        'countInAnotherUsers' => 0,
                    ]
                ]
            ]
        ];

        $variants = ['+375336443266', '375336443266'];
        foreach ($variants as $variant) {
            $I->sendGet('/v1/contact-phone?search='.urlencode($variant));
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->comment('Test variant '.$variant);
            $I->seeResponseContainsJson($unknownResponse);
            $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        }

        $I->sendGet('/v1/contact-phone?search='.urlencode('+380'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        $I->sendGet('/v1/contact-phone?search='.urlencode('963643'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        $I->sendGet('/v1/contact-phone?search='.urlencode('+380 50 123 4567'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        $I->sendGet('/v1/contact-phone/pending');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'phone' => '+79636417381',
                        'phones' => ['+79636417381'],
                        'displayName' => 'Vitya Pishuk',
                        'status' => 'pending',
                        'countInAnotherUsers' => 2,
                    ],
                    [
                        'phone' => '+79262250588',
                        'phones' => ['+79262250588'],
                        'displayName' => '+79262250588',
                        'status' => 'pending',
                        'countInAnotherUsers' => 0,
                    ],
                ]
            ]
        ]);
        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(2, $items);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $manager->persist(new Invite($main, PhoneNumberUtil::getInstance()->parse('+375336443266')));
                $manager->flush();
            }
        }, true);

        $sendRemindResponse = [
            'response' => [
                'items' => [
                    0 => [
                        'phone' => '+375336443266',
                        'displayName' => '+375336443266',
                        'status' => 'send_reminder',
                        'countInAnotherUsers' => 0,
                    ]
                ]
            ]
        ];

        foreach ($variants as $variant) {
            $I->sendGet('/v1/contact-phone?search='.urlencode($variant));
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->comment('Test variant '.$variant);
            $I->seeResponseContainsJson($sendRemindResponse);
            $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        }
    }

    public function testPagination(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                for ($i = 10; $i < 20; $i++) {
                    $contact = new PhoneContact(
                        $main,
                        '+796364176'.$i,
                        PhoneNumberUtil::getInstance()->parse('+796364176'.$i),
                        'User contact '.$i
                    );
                    $contact->sort = $i;
                    $manager->persist($contact);
                }

                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/contact-phone?limit=5');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->assertEquals('User contact 10', $I->grabDataFromResponseByJsonPath('$.response.items[0].displayName')[0]);
        $I->assertEquals('User contact 11', $I->grabDataFromResponseByJsonPath('$.response.items[1].displayName')[0]);
        $I->assertEquals('User contact 12', $I->grabDataFromResponseByJsonPath('$.response.items[2].displayName')[0]);
        $I->assertEquals('User contact 13', $I->grabDataFromResponseByJsonPath('$.response.items[3].displayName')[0]);
        $I->assertEquals('User contact 14', $I->grabDataFromResponseByJsonPath('$.response.items[4].displayName')[0]);

        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNotNull($lastValue);

        //Next page
        $I->sendGet('/v1/contact-phone?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->assertEquals('User contact 15', $I->grabDataFromResponseByJsonPath('$.response.items[0].displayName')[0]);
        $I->assertEquals('User contact 16', $I->grabDataFromResponseByJsonPath('$.response.items[1].displayName')[0]);
        $I->assertEquals('User contact 17', $I->grabDataFromResponseByJsonPath('$.response.items[2].displayName')[0]);
        $I->assertEquals('User contact 18', $I->grabDataFromResponseByJsonPath('$.response.items[3].displayName')[0]);
        $I->assertEquals('User contact 19', $I->grabDataFromResponseByJsonPath('$.response.items[4].displayName')[0]);

        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNull($lastValue);
    }

    public function testListWithAdditionalPhones(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $danielRegisteredPhoneNumber = '+79236417683';
                $danielInvitedPhoneNumber = '+79236417682';
                $danielUnregisteredPhoneNumber = '+79236417681';

                $util = PhoneNumberUtil::getInstance();

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $danielPhoneContact = new PhoneContact(
                    $main,
                    $danielRegisteredPhoneNumber,
                    $util->parse($danielRegisteredPhoneNumber),
                    'Daniel'
                );
                $danielPhoneContact->addAdditionalPhoneNumber(new PhoneContactNumber(
                    $danielPhoneContact,
                    $danielInvitedPhoneNumber,
                    $util->parse($danielInvitedPhoneNumber)
                ));
                $danielPhoneContact->addAdditionalPhoneNumber(new PhoneContactNumber(
                    $danielPhoneContact,
                    $danielUnregisteredPhoneNumber,
                    $util->parse($danielUnregisteredPhoneNumber)
                ));

                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $alice->phone = $util->parse($danielRegisteredPhoneNumber);
                $manager->persist($alice);

                $invite = new Invite($main, $util->parse($danielInvitedPhoneNumber));
                $manager->persist($invite);

                $manager->persist($danielPhoneContact);
                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v1/contact-phone');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'phone' => '+79236417683',
                        'phones' => [
                            '+79236417681',
                            '+79236417682',
                            '+79236417683',
                        ],
                        'additionalPhones' => [
                            [
                                'phone' => '+79236417681',
                                'status' => 'new',
                            ],
                            [
                                'phone' => '+79236417682',
                                'status' => 'pending',
                            ],
                            [
                                'phone' => '+79236417683',
                                'status' => 'invited',
                            ],
                        ],
                        'displayName' => 'Daniel',
                        'status' => 'new',
                        'countInAnotherUsers' => 0,
                    ],
                ],
            ],
        ]);
    }

    public function testPendingInvites(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                //Phone number used in contacts another users and invited by current user
                $phoneNumberA = '+79606417683';
                //Phone number from phone contact numbers of current user, but it's not main number (additional number)
                $phoneNumberB = '+79606417684';
                //Main phone contact number
                $phoneNumberC = '+79606417685';
                //Phone number of already registered user
                $phoneNumberD = '+79606417686';

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $util = PhoneNumberUtil::getInstance();

                //Situation #1 with phone number A
                $phoneContact = new PhoneContact($mike, $phoneNumberA, $util->parse($phoneNumberA), 'Contact 1');
                $phoneContact->addAdditionalPhoneNumber(
                    new PhoneContactNumber($phoneContact, '+79630000001', $util->parse('+79630000001'))
                );
                $manager->persist($phoneContact);
                $manager->persist(new Invite($main, $util->parse($phoneNumberA)));
                $manager->flush();

                //Situation #2 with phone number B
                $phoneContact = new PhoneContact($main, '+79630000000', $util->parse('+79630000000'), 'Contact 2');
                $phoneContact->addAdditionalPhoneNumber(
                    new PhoneContactNumber($phoneContact, $phoneNumberB, $util->parse($phoneNumberB))
                );
                $manager->persist(new Invite($main, $util->parse('+79630000000')));
                $manager->persist($phoneContact);
                $manager->persist(new Invite($main, $util->parse($phoneNumberB)));
                $manager->flush();

                //Situation #3 with phone number C
                $phoneContact = new PhoneContact($main, $phoneNumberC, $util->parse($phoneNumberC), 'Contact 3');
                $phoneContact->addAdditionalPhoneNumber(
                    new PhoneContactNumber($phoneContact, '+79630000003', $util->parse('+79630000003'))
                );
                $manager->persist($phoneContact);
                $manager->persist(new Invite($main, $util->parse($phoneNumberC)));
                $manager->flush();

                //Situation #3 with phone number D
                $newUser = new User();
                $newUser->phone = $util->parse($phoneNumberD);
                $newUser->username = 'newuser';
                $newUser->state = User::STATE_VERIFIED;
                $manager->persist($newUser);

                $invite = new Invite($mike, $util->parse($phoneNumberD));
                $invite->registeredUser = $newUser;
                $manager->persist($invite);
                $manager->persist(new Invite($main, $util->parse($phoneNumberD)));
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/contact-phone/pending');
        $I->seeResponseCodeIs(HttpCode::OK);
        
        $I->seeResponseContainsJson([
            'response' => [
                'items' => [
                    [
                        'phone' => '+79606417683',
                        'phones' => ['+79606417683',],
                        'additionalPhones' => [
                            ['phone' => '+79606417683', 'status' => 'pending'],
                        ],
                        'displayName' => '+79606417683',
                        'thumbnail' => null,
                        'status' => 'pending',
                        'countInAnotherUsers' => 1,
                    ],
                    [
                        'phone' => '+79606417684',
                        'phones' => ['+79606417684', '+79630000000',],
                        'additionalPhones' => [
                            ['phone' => '+79630000000', 'status' => 'pending'],
                            ['phone' => '+79606417684', 'status' => 'pending'],
                        ],
                        'displayName' => 'Contact 2',
                        'thumbnail' => null,
                        'status' => 'pending',
                        'countInAnotherUsers' => 0,
                    ],
                    [
                        'phone' => '+79606417685',
                        'phones' => ['+79606417685', '+79630000003'],
                        'additionalPhones' => [
                            ['phone' => '+79606417685', 'status' => 'pending'],
                            ['phone' => '+79630000003', 'status' => 'new'],
                        ],
                        'displayName' => 'Contact 3',
                        'thumbnail' => null,
                        'status' => 'pending',
                        'countInAnotherUsers' => 0,
                    ],
                ],
                'lastValue' => null,
            ]
        ]);
    }
}
