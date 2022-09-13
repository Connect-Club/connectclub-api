<?php

namespace App\Tests\V2\User;

use App\Entity\Club\Club;
use App\Entity\Club\ClubParticipant;
use App\Entity\Follow\Follow;
use App\Entity\Invite\Invite;
use App\Entity\Photo\Image;
use App\Entity\User;
use App\Entity\User\Device;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Ramsey\Uuid\Uuid;
use Symfony\Bridge\PhpUnit\ClockMock;

class UserCest extends BaseCest
{
    const BASE_TIME = 1000;

    const USER_SLIM_FORMAT_RESPONSE_JSON = [
        'id' => 'string',
        'avatar' => 'string|null',
        'name' => 'string',
        'surname' => 'string',
        'displayName' => 'string',
        'about' => 'string',
        'username' => 'string',
        'isDeleted' => 'boolean',
        'createdAt' => 'integer',
        'online' => 'boolean',
        'lastSeen' => 'integer',
        'badges' => 'array',
        'shortBio' => 'string|null',
        'longBio' => 'string|null',
        'twitter' => 'string|null',
        'instagram' => 'string|null',
        'linkedin' => 'string|null',
    ];

    const USER_MIDDLE_RESPONSE_JSON = self::USER_SLIM_FORMAT_RESPONSE_JSON + [
        'isFollowing' => 'boolean',
        'isFollows' => 'boolean',
    ];

    const USER_FORMAT_RESPONSE_JSON = self::USER_MIDDLE_RESPONSE_JSON + [
        'joinedBy' => 'array|null',
        'interests' => 'array',
        'followers' => 'integer',
        'following' => 'integer',
        'isSuperCreator' => 'boolean',
        'memberOf' => 'array',
        'invitedTo' => 'array|null',
    ];

    const USER_FORMAT_WITH_BLOCKED_RESPONSE_JSON = self::USER_FORMAT_RESPONSE_JSON + [
        'isBlocked' => 'boolean',
    ];

    const USER_ADMIN_FORMAT_RESPONSE_JSON = self::USER_FORMAT_RESPONSE_JSON + [
        'id' => 'string',
        'avatar' => 'string|null',
        'name' => 'string',
        'surname' => 'string',
        'displayName' => 'string',
        'about' => 'string',
        'username' => 'string',
        'isDeleted' => 'boolean',
        'createdAt' => 'integer',
        'online' => 'boolean',
        'lastSeen' => 'integer',
        'freeInvites' => 'integer',
        'state' => 'string',
        'bannedBy' => 'array|null',
        'deletedBy' => 'array|null',
        'deleteComment' => 'string|null',
        'banComment' => 'string|null',
        'phone' => 'string|null',
        'devices' => 'array',
        'city' => 'string|null',
        'country' => 'string|null',
        'source' => 'string|null',
    ];

    public function testList(ApiTester $I)
    {
        /** @var User $main */
        $main = $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
        /** @var User $alice */
        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);
        /** @var User $bob */
        $bob = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]);

        ClockMock::withClockMock(self::BASE_TIME);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $userRepository = $manager->getRepository(User::class);

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mike->state = User::STATE_BANNED;

                $manager->persist(new Follow($alice, $main));
                $manager->persist(new Follow($main, $bob));
                $manager->persist(new Follow($main, $mike));
                $manager->persist(new Follow($mike, $main));

                $mike->username = 'mike';
                $main->username = 'main';
                $main->about = 'About main';
                $alice->username = 'alice';
                $bob->username = 'bob';

                $manager->persist($main);
                $manager->persist($alice);
                $manager->persist($bob);

                $manager->persist(new Device(Uuid::uuid4(), $main, 'ios', null, null, 'ru', 'iphone 12'));
                $manager->persist(new Device(Uuid::uuid4(), $main, 'macos', null, null, 'ru', null));

                $club = new Club($main, 'Main Club');
                $club->avatar = new Image('test', 'original-image.png', 'image.png', $main);
                $manager->persist($club);
                $manager->persist($club->avatar);
                $manager->persist(new ClubParticipant($club, $alice, $main));

                ClockMock::sleep(100);

                $club = new Club($main, 'Second Main Club');
                $club->avatar = new Image('test', 'original-image.png', 'image.png', $main);
                $manager->persist($club);
                $manager->persist($club->avatar);
                $manager->persist(new ClubParticipant($club, $alice, $main));

                $aliceInvite = $manager->getRepository(Invite::class)->findOneBy([
                    'registeredUser' => $alice,
                ]);
                $aliceInvite->club = $club;
                $aliceInvite->author = $main;

                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPost('/v2/users', json_encode([$main->id, $alice->id, $bob->id]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseMatchesJsonTypeStrict([
            self::USER_FORMAT_WITH_BLOCKED_RESPONSE_JSON,
            self::USER_FORMAT_WITH_BLOCKED_RESPONSE_JSON,
            self::USER_FORMAT_WITH_BLOCKED_RESPONSE_JSON,
        ]);

        /** @var Club $mainClub */
        $mainClub = $I->grabEntityFromRepository(Club::class, [
            'title' => 'Main Club',
        ]);

        /** @var Club $secondMainClub */
        $secondMainClub = $I->grabEntityFromRepository(Club::class, [
            'title' => 'Second Main Club',
        ]);

        $I->seeResponseContainsJson([
            'response' => [
                [
                    'id' => '1',
                    'avatar' => null,
                    'name' => 'main_user_name',
                    'surname' => 'main_user_surname',
                    'displayName' => 'main_user_name main_user_surname',
                    'about' => 'About main',
                    'username' => 'main',
                    'isDeleted' => false,
                    'isFollowing' => false,
                    'isFollows' => false,
                    'followers' => 1,
                    'following' => 1,
                    'invitedTo' => null,
                ],
                [
                    'id' => '2',
                    'avatar' => null,
                    'name' => 'alice_user_name',
                    'surname' => 'alice_user_surname',
                    'displayName' => 'alice_user_name alice_user_surname',
                    'about' => '',
                    'username' => 'alice',
                    'isDeleted' => false,
                    'isFollowing' => false,
                    'isFollows' => true,
                    'followers' => 0,
                    'following' => 1,
                    'invitedTo' => [
                        'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/image.png',
                        'by' => [
                            'id' => $main->id,
                            'displayName' => $main->getFullNameOrId(),
                        ],
                    ],
                ],
                [
                    'avatar' => null,
                    'name' => 'bob_user_name',
                    'surname' => 'bob_user_surname',
                    'displayName' => 'bob_user_name bob_user_surname',
                    'about' => '',
                    'username' => 'bob',
                    'isDeleted' => false,
                    'isFollowing' => true,
                    'isFollows' => false,
                    'followers' => 1,
                    'following' => 0,
                    'memberOf' => [],
                    'invitedTo' => null,
                ],
            ]
        ]);

        $this->assertMemberOf($I, [
            'main_user_name' => [
                [
                    'id' => $mainClub->id->toString(),
                    'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/image.png',
                    'clubRole' => 'owner',
                    'title' => 'Main Club',
                ],
                [
                    'id' => $secondMainClub->id->toString(),
                    'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/image.png',
                    'clubRole' => 'owner',
                    'title' => 'Second Main Club',
                ],
            ],
            'alice_user_name' => [
                [
                    'id' => $mainClub->id->toString(),
                    'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/image.png',
                    'clubRole' => 'member',
                    'title' => 'Main Club',
                ],
                [
                    'id' => $secondMainClub->id->toString(),
                    'avatar' => 'https://pics.connect.lol/:WIDTHx:HEIGHT/image.png',
                    'clubRole' => 'member',
                    'title' => 'Second Main Club',
                ],
            ],
            'bob_user_name' => [],
        ]);

        $this->assertInterests($I, [
            'main_user_name' => [
                'Interest_1',
                'Interest_2',
            ],
            'alice_user_name' => [
                'Interest_1',
                'Interest_2',
            ],
            'bob_user_name' => [
                'Interest_1',
                'Interest_2',
            ],
        ]);

        $I->sendGet('/v2/users');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseMatchesJsonTypeStrict([
            'totalCount' => 'integer',
            'lastValue' => 'integer|null',
            'items' => [
                self::USER_ADMIN_FORMAT_RESPONSE_JSON,
                self::USER_ADMIN_FORMAT_RESPONSE_JSON,
                self::USER_ADMIN_FORMAT_RESPONSE_JSON,
                self::USER_ADMIN_FORMAT_RESPONSE_JSON,
            ],
        ]);

        $I->seeResponseContainsJson([
            'response' => [
                'totalCount' => 4,
                'lastValue' => null,
                'items' => [
                    [
                        'id' => '4',
                        'avatar' => null,
                        'name' => 'Mike',
                        'surname' => 'Mike',
                        'displayName' => 'Mike Mike',
                        'about' => '',
                        'username' => 'mike',
                        'isDeleted' => false,
                        'interests' => [
                        ],
                        'devices' => [],
                    ],
                    [
                        'id' => '3',
                        'avatar' => null,
                        'name' => 'bob_user_name',
                        'surname' => 'bob_user_surname',
                        'displayName' => 'bob_user_name bob_user_surname',
                        'about' => '',
                        'username' => 'bob',
                        'isDeleted' => false,
                        'interests' => [
                            ['name' => 'Interest_1'],
                            ['name' => 'Interest_2'],
                        ],
                        'devices' => [],
                    ],
                    [
                        'id' => '2',
                        'avatar' => null,
                        'name' => 'alice_user_name',
                        'surname' => 'alice_user_surname',
                        'displayName' => 'alice_user_name alice_user_surname',
                        'about' => '',
                        'username' => 'alice',
                        'isDeleted' => false,
                        'interests' => [
                            ['name' => 'Interest_1'],
                            ['name' => 'Interest_2'],
                        ],
                        'devices' => [],
                    ],
                    [
                        'id' => '1',
                        'avatar' => null,
                        'name' => 'main_user_name',
                        'surname' => 'main_user_surname',
                        'displayName' => 'main_user_name main_user_surname',
                        'about' => 'About main',
                        'username' => 'main',
                        'isDeleted' => false,
                        'interests' => [
                            ['name' => 'Interest_1'],
                            ['name' => 'Interest_2'],
                        ],
                        'devices' => [
                            'ios: iphone 12',
                            'macos: ',
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function assertMemberOf(ApiTester $I, array $expectedItems): void
    {
        $actualItems = $I->grabDataFromResponseByJsonPath('$.response')[0];

        uksort($expectedItems, fn($name1, $name2) => strcmp($name1, $name2));
        uasort($actualItems, fn($item1, $item2) => strcmp($item1['name'], $item2['name']));

        $normalizedActualItems = [];
        foreach ($actualItems as $actualItem) {
            $normalizedActualItems[$actualItem['name']] = $actualItem['memberOf'];

            usort(
                $normalizedActualItems[$actualItem['name']],
                fn($memberOf1, $memberOf2) => strcmp($memberOf1['id'], $memberOf2['id'])
            );

            usort(
                $expectedItems[$actualItem['name']],
                fn($memberOf1, $memberOf2) => strcmp($memberOf1['id'], $memberOf2['id'])
            );
        }

        $I->assertEquals($expectedItems, $normalizedActualItems);
    }

    private function assertInterests(ApiTester $I, array $expectedItems): void
    {
        $actualItems = $I->grabDataFromResponseByJsonPath('$.response')[0];

        uksort($expectedItems, fn($name1, $name2) => strcmp($name1, $name2));
        uasort($actualItems, fn($item1, $item2) => strcmp($item1['name'], $item2['name']));

        $normalizedActualItems = [];
        foreach ($actualItems as $actualItem) {
            $normalizedActualItems[$actualItem['name']] = array_map(
                fn(array $interest) => $interest['name'],
                $actualItem['interests']
            );

            usort(
                $normalizedActualItems[$actualItem['name']],
                fn($interest1, $interest2) => strcmp($interest1, $interest2)
            );

            usort(
                $expectedItems[$actualItem['name']],
                fn($interest1, $interest2) => strcmp($interest1, $interest2)
            );
        }

        $I->assertEquals($expectedItems, $normalizedActualItems);
    }
}
