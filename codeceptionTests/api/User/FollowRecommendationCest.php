<?php

namespace App\Tests\User;

use App\Client\PeopleMatchingClient;
use App\DataFixtures\AccessTokenFixture;
use App\Entity\Follow\Follow;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MatchingClient;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\RequestException;
use libphonenumber\PhoneNumberUtil;
use Mockery;
use RuntimeException;

class FollowRecommendationCest extends BaseCest
{
    public function testRecommendation(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $util = PhoneNumberUtil::getInstance();
                $userRepository = $manager->getRepository('App:User');

                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);
                $mike->state = User::STATE_VERIFIED;
                $manager->persist($mike);

                foreach ($manager->getRepository('App:Follow\Follow')->findBy(['follower' => $main]) as $follow) {
                    $manager->remove($follow);
                }

                for ($i = 0; $i < 10; $i++) {
                    $phoneNumber = '+796364176'.(10 + $i);
                    $phoneNumberObject = $util->parse($phoneNumber);

                    $user = new User();
                    $user->name = 'Name-'.$i;
                    $user->surname = 'Surname-'.$i;
                    $user->phone = $phoneNumberObject;
                    $user->email = 'user-'.$i.'@test.ru';
                    $user->state = User::STATE_VERIFIED;
                    $manager->persist($user);

                    if ($i <= 5) {
                        $phoneContact = new User\PhoneContact($main, $phoneNumber, $phoneNumberObject, 'User '.$i);
                        $manager->persist($phoneContact);
                    }

                    $this->setReference('user-'.$i, $user);
                }

                $phoneNumber = '+79636417412';
                $phoneNumberObject = $util->parse($phoneNumber);
                $manager->persist(new User\PhoneContact(
                    $main,
                    $phoneNumber,
                    $phoneNumberObject,
                    'User '.$i
                ));
                $alice->phone = $phoneNumberObject;
                $manager->persist($alice);

                $phoneNumber = '+79636417411';
                $phoneNumberObject = $util->parse($phoneNumber);
                $manager->persist(new User\PhoneContact(
                    $main,
                    $phoneNumber,
                    $phoneNumberObject,
                    'User '.$i
                ));
                $bob->phone = $phoneNumberObject;
                $manager->persist($bob);

                for ($i = 0; $i < 10; $i++) {
                    $manager->persist(new Follow(
                        $this->getReference('user-'.$i),
                        $alice
                    ));

                    if ($i <= 5) {
                        $manager->persist(new Follow(
                            $this->getReference('user-'.$i),
                            $bob
                        ));
                    }
                }

                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/recommended/contacts?limit=5');
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertEquals(5, $lastValue);
        $recommended = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(5, $recommended);

        $I->assertEquals('Mike', $recommended[0]['name']);
        $I->assertEquals('alice_user_name', $recommended[1]['name']);
        $I->assertEquals('bob_user_name', $recommended[2]['name']);
        $I->assertEquals('Name-0', $recommended[3]['name']);
        $I->assertEquals('Name-1', $recommended[4]['name']);

        $I->sendGet('/v1/follow/recommended/contacts?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);
        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertEquals(null, $lastValue);
        $recommended = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
        $I->assertCount(4, $recommended);

        $I->assertEquals('Name-2', $recommended[0]['name']);
        $I->assertEquals('Name-3', $recommended[1]['name']);
        $I->assertEquals('Name-4', $recommended[2]['name']);
        $I->assertEquals('Name-5', $recommended[3]['name']);
    }

    public function testFollowingSimilarRecommendation(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface
        {
            public function getDependencies(): array
            {
                return [AccessTokenFixture::class];
            }

            public function load(ObjectManager $manager)
            {
                $interestGroup = new InterestGroup('test interest');
                $manager->persist($interestGroup);

                $interestA = new Interest($interestGroup, 'Interest A', 0, false);
                $manager->persist($interestA);
                $interestB = new Interest($interestGroup, 'Interest B', 0, false);
                $manager->persist($interestB);
                $interestC = new Interest($interestGroup, 'Interest C', 0, false);
                $manager->persist($interestC);

                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $main->clearInterests();
                $main->addInterest($interestA);
                $main->addInterest($interestB);

                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $alice->clearInterests();
                $alice->recommendedForFollowingPriority = 1;

                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $bob->recommendedForFollowingPriority = 2;
                $bob->clearInterests();

                $manager->persist($alice);
                $manager->persist($bob);
                $manager->persist($main);

                for ($i = 0; $i < 5; $i++) {
                    $user = new User();
                    $user->email = 'user-'.$i.'@test.ru';
                    $user->name = 'user-name-'.$i;
                    $user->surname = 'user-surname-'.$i;
                    $user->state = User::STATE_VERIFIED;
                    $user->addInterest($interestA);

                    $manager->persist($user);
                    $this->setReference('user-'.$i, $user);
                }

                for ($i = 5; $i < 10; $i++) {
                    $user = new User();
                    $user->email = 'user-'.$i.'@test.ru';
                    $user->name = 'user-name-'.$i;
                    $user->surname = 'user-surname-'.$i;
                    $user->state = User::STATE_VERIFIED;
                    $user->addInterest($interestB);

                    $manager->persist($user);
                    $this->setReference('user-'.$i, $user);
                }

                $user = $this->getReference('user-9');
                for ($i = 0; $i < 9; $i++) {
                    $manager->persist(new Follow($this->getReference('user-'.$i), $user));
                }

                $manager->flush();
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGet('/v1/follow/recommended/similar?limit=5');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->assertCount(5, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        $I->assertEquals('alice_user_name', $I->grabDataFromResponseByJsonPath('$.response.items[0].name')[0]);
        $I->assertEquals('bob_user_name', $I->grabDataFromResponseByJsonPath('$.response.items[1].name')[0]);
        $I->assertEquals('user-name-9', $I->grabDataFromResponseByJsonPath('$.response.items[2].name')[0]);
        $I->assertEquals('user-name-1', $I->grabDataFromResponseByJsonPath('$.response.items[3].name')[0]);
        $I->assertEquals('user-name-2', $I->grabDataFromResponseByJsonPath('$.response.items[4].name')[0]);

        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNotNull($lastValue);

        $I->comment('Go to next page '.$lastValue);
        $I->sendGet('/v1/follow/recommended/similar?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->assertCount(5, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        $I->assertEquals('user-name-3', $I->grabDataFromResponseByJsonPath('$.response.items[0].name')[0]);
        $I->assertEquals('user-name-4', $I->grabDataFromResponseByJsonPath('$.response.items[1].name')[0]);
        $I->assertEquals('user-name-0', $I->grabDataFromResponseByJsonPath('$.response.items[2].name')[0]);
        $I->assertEquals('user-name-6', $I->grabDataFromResponseByJsonPath('$.response.items[3].name')[0]);
        $I->assertEquals('user-name-7', $I->grabDataFromResponseByJsonPath('$.response.items[4].name')[0]);

        $lastValue = $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
        $I->assertNotNull($lastValue);

        $I->comment('Go to next page again '.$lastValue);
        $I->sendGet('/v1/follow/recommended/similar?limit=5&lastValue='.$lastValue);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->assertCount(2, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);

        $I->assertEquals('user-name-8', $I->grabDataFromResponseByJsonPath('$.response.items[0].name')[0]);
        $I->assertEquals('user-name-5', $I->grabDataFromResponseByJsonPath('$.response.items[1].name')[0]);
    }

    public function testRecommendationByContactsAndInterests(ApiTester $I): void
    {
        $I->markTestSkipped();

        $this->mockPeopleMatchingService($I);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            private ObjectManager $manager;
            private User $mainUser;
            private int $phoneNumberCount = 0;

            public function getDependencies(): array
            {
                return [
                    AccessTokenFixture::class
                ];
            }

            public function load(ObjectManager $manager)
            {
                $this->manager = $manager;

                $this->mainUser = $this->getMainUser();

                $this->createUsersWithSimilarInterests(4);
                $friends = $this->createFriends(4);
                $this->createFriendsOfFriends($friends, 4);
                $this->createRecommendedUsers(4);

                $manager->flush();
            }

            private function createUsersWithSimilarInterests(int $quantity): void
            {
                $sport = new InterestGroup('Sport');
                $this->manager->persist($sport);
                $music = new InterestGroup('Music');
                $this->manager->persist($music);

                $hiking = new Interest($sport, 'Hiking');
                $this->manager->persist($hiking);
                $piano = new Interest($music, 'Piano');
                $this->manager->persist($piano);

                $this->mainUser->addInterest($hiking);
                $this->mainUser->addInterest($piano);

                for ($i = $quantity - 1; $i >= 0; $i--) {
                    $user = $this->createNextUser('similar-interests', $i);

                    $user->addInterest($hiking);
                    $user->addInterest($piano);
                }

                $user = $this->createNextUser('similar-interests', $quantity);
                $user->addInterest($hiking);
                $user->addInterest($piano);

                $this->follow($user);
            }

            private function createFriends(int $quantity): array
            {
                $friends = [];
                for ($i = 0; $i < $quantity; $i++) {
                    $user = $this->createNextUser('friend', $i);
                    $contact = $this->createPhoneContact($this->mainUser, $user);

                    $contact->sort = $quantity - $i;

                    $friends[] = $user;
                }

                $user = $this->createNextUser('friend', $i + 1);
                $this->createPhoneContact($this->mainUser, $user);
                $this->follow($user);

                return $friends;
            }

            /**
             * @param User[] $friends
             */
            private function createFriendsOfFriends(array $friends, int $quantity): void
            {
                $i = $quantity - 1;
                if (!$friends) {
                    return;
                }

                while (true) {
                    foreach ($friends as $friend) {
                        if ($i < 0) {
                            break 2;
                        }

                        $user = $this->createNextUser('friend-of-friend', $i);
                        $this->createPhoneContact($friend, $user);

                        $i--;
                    }
                }

                $user = $this->createNextUser('friend-of-friend', $quantity);
                $this->createPhoneContact($friend, $user);

                $this->follow($user);
            }

            private function createRecommendedUsers(int $quantity): void
            {
                for ($i = $quantity - 1; $i >= 0; $i--) {
                    $user = $this->createNextUser('recommended', $i);
                    $user->recommendedForFollowingPriority = $i;
                }
            }

            private function follow($user): void
            {
                $follow = new Follow($this->mainUser, $user);

                $this->manager->persist($follow);
            }

            private function getMainUser(): User
            {
                /** @var UserRepository $userRepository */
                $userRepository = $this->manager->getRepository(User::class);
                return $userRepository->findOneBy(['email' => FollowCest::MAIN_USER_EMAIL]);
            }

            private function createNextUser(string $prefix, int $i): User
            {
                $phoneUtil = PhoneNumberUtil::getInstance();

                $phoneNumber = '+7' . str_pad($this->phoneNumberCount, 10, '0', STR_PAD_LEFT);

                $user = new User();
                $user->username = "{$prefix}-{$i}";
                $user->email = "{$prefix}-{$i}@email.com";
                $user->phone = $phoneUtil->parse($phoneNumber);
                $user->state = User::STATE_VERIFIED;
                $this->manager->persist($user);

                $this->phoneNumberCount++;

                return $user;
            }

            private function createPhoneContact(User $owner, User $user): User\PhoneContact
            {
                $contact = new User\PhoneContact(
                    $owner,
                    $user->phone,
                    $user->phone,
                    "Contact for {$user->username}"
                );

                $this->manager->persist($contact);

                return $contact;
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v1/follow/recommended', [
            'limit' => 21,
        ]);

        $this->assertResponseItems($I, [
            'recommended-0',
            'recommended-1',
            'recommended-2',
            'recommended-3',
            'friend-0',
            'friend-1',
            'friend-2',
            'friend-3',
            'friend-of-friend-0',
            'friend-of-friend-1',
            'friend-of-friend-2',
            'friend-of-friend-3',
            'similar-interests-0',
            'similar-interests-1',
            'similar-interests-2',
            'similar-interests-3',
        ]);
    }

    public function testRecommendationByContactsAndInterestsPaginationDuplicates(ApiTester $I)
    {
        $I->markTestSkipped();

        $this->mockPeopleMatchingService($I);

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            private ObjectManager $manager;
            private int $phoneNumberCount = 0;

            public function getDependencies(): array
            {
                return [
                    AccessTokenFixture::class
                ];
            }

            public function load(ObjectManager $manager)
            {
                $this->manager = $manager;

                $friend1 = $this->createNextUser('friend', 0);
                $this->createPhoneContact($this->getMainUser(), $friend1);

                $friend2 = $this->createNextUser('friend', 1);
                $this->createPhoneContact($this->getMainUser(), $friend2);

                $recommended = $this->createNextUser('recommended', 0);
                $recommended->recommendedForFollowingPriority = 1;

                $friend1->recommendedForFollowingPriority = 3;

                $manager->flush();
            }

            private function getMainUser(): User
            {
                /** @var UserRepository $userRepository */
                $userRepository = $this->manager->getRepository(User::class);
                return $userRepository->findOneBy(['email' => FollowCest::MAIN_USER_EMAIL]);
            }

            private function createNextUser(string $prefix, int $i): User
            {
                $phoneUtil = PhoneNumberUtil::getInstance();

                $phoneNumber = '+7' . str_pad($this->phoneNumberCount, 10, '0', STR_PAD_LEFT);

                $user = new User();
                $user->username = "{$prefix}-{$i}";
                $user->email = "{$prefix}-{$i}@email.com";
                $user->phone = $phoneUtil->parse($phoneNumber);
                $user->state = User::STATE_VERIFIED;
                $this->manager->persist($user);

                $this->phoneNumberCount++;

                return $user;
            }

            private function createPhoneContact(User $owner, User $user): User\PhoneContact
            {
                $contact = new User\PhoneContact(
                    $owner,
                    $user->phone,
                    $user->phone,
                    "Contact for {$user->username}"
                );

                $this->manager->persist($contact);

                return $contact;
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v1/follow/recommended', [
            'limit' => 10
        ]);
        $this->assertResponseItems($I, [
            'recommended-0',
            'friend-0',
            'friend-1',
        ]);
    }

    public function testRecommendationByContactsAndInterestsContactDuplicates(ApiTester $I)
    {
        $I->markTestSkipped();

        $I->loadFixtures(new class extends Fixture implements DependentFixtureInterface {
            private ObjectManager $manager;
            private int $phoneNumberCount = 0;

            public function getDependencies(): array
            {
                return [
                    AccessTokenFixture::class
                ];
            }

            public function load(ObjectManager $manager)
            {
                $this->manager = $manager;

                $mainUser = $this->getMainUser();

                $friend1 = $this->createNextUser('friend', 0);
                $this->createPhoneContact($mainUser, $friend1);

                $friend2 = $this->createNextUser('friend', 1);
                $this->createPhoneContact($mainUser, $friend2);

                $friend3 = $this->createNextUser('friend', 2);
                $this->createPhoneContact($mainUser, $friend3);

                $friendOfFriend = $this->createNextUser('friend-of-friend', 0);
                $this->createPhoneContact($friend1, $friendOfFriend);
                $this->createPhoneContact($friend2, $friendOfFriend);
                $this->createPhoneContact($friend3, $friendOfFriend);

                $friendOfFriend = $this->createNextUser('friend-of-friend', 1);
                $this->createPhoneContact($friend1, $friendOfFriend);
                $this->createPhoneContact($friend2, $friendOfFriend);
                $this->createPhoneContact($friend3, $friendOfFriend);

                $manager->flush();
            }

            private function getMainUser(): User
            {
                /** @var UserRepository $userRepository */
                $userRepository = $this->manager->getRepository(User::class);
                return $userRepository->findOneBy(['email' => FollowCest::MAIN_USER_EMAIL]);
            }

            private function createNextUser(string $prefix, int $i): User
            {
                $phoneUtil = PhoneNumberUtil::getInstance();

                $phoneNumber = '+7' . str_pad($this->phoneNumberCount, 10, '0', STR_PAD_LEFT);

                $user = new User();
                $user->username = "{$prefix}-{$i}";
                $user->email = "{$prefix}-{$i}@email.com";
                $user->phone = $phoneUtil->parse($phoneNumber);
                $user->state = User::STATE_VERIFIED;
                $this->manager->persist($user);

                $this->phoneNumberCount++;

                return $user;
            }

            private function createPhoneContact(User $owner, User $user): User\PhoneContact
            {
                $contact = new User\PhoneContact(
                    $owner,
                    $user->phone,
                    $user->phone,
                    "Contact for {$user->username}"
                );

                $this->manager->persist($contact);

                return $contact;
            }
        }, false);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $I->sendGet('/v1/follow/recommended', [
            'limit' => 5
        ]);
        $this->assertResponseItems($I, [
            'friend-0',
            'friend-1',
            'friend-2',
            'friend-of-friend-1',
            'friend-of-friend-0',
        ]);
    }

    private function mockPeopleMatchingService(ApiTester $I)
    {
        $mock = Mockery::mock(MatchingClient::class);
        $mock->shouldReceive('findPeopleMatchingForUser')->andThrow(new RuntimeException('Unexpected response'));

        $I->mockService(MatchingClient::class, $mock);
    }

    private function assertResponseItems(ApiTester $I, array $userNames): ?string
    {
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = $I->grabDataFromResponseByJsonPath('$.response')[0];
        $I->assertEquals($userNames, array_column($response['items'], 'username'));

        return $response['lastValue'];
    }
}
