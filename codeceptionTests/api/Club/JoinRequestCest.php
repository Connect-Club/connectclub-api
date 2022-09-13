<?php

namespace App\Tests\Club;

use App\Client\ElasticSearchClientBuilder;
use App\Entity\Club\Club;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class JoinRequestCest extends BaseCest
{
    public function testNullSearch(ApiTester $I)
    {
        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(0)
        );

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $owner = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $club = new Club($owner, 'My Club 0');
                $club->id = Uuid::fromString('84ac6e6d-4db8-4013-b078-5aac1827ba0e');
                $manager->persist($club);

                $author = new User();
                $manager->persist($author);
                $joinRequest = new JoinRequest($club, $author);
                $manager->persist($joinRequest);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $clubId = '84ac6e6d-4db8-4013-b078-5aac1827ba0e';

        $I->sendGet('/v1/club/'.$clubId.'/join-requests');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(
            1,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0]
        );
    }

    public function testSearchNoUsersFound(ApiTester $I)
    {
        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(1, 'alice', [])
        );

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $owner = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $club = new Club($owner, 'My Club 0');
                $club->id = Uuid::fromString('84ac6e6d-4db8-4013-b078-5aac1827ba0e');
                $manager->persist($club);

                $userRepository = $manager->getRepository(User::class);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $joinRequest = new JoinRequest($club, $alice);
                $manager->persist($joinRequest);
                $manager->flush();
            }
        }, true);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $clubId = '84ac6e6d-4db8-4013-b078-5aac1827ba0e';

        $I->sendGet('/v1/club/'.$clubId.'/join-requests?search=alice');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(
            0,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0]
        );
    }

    public function testSearchUsersFound(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $owner = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $club = new Club($owner, 'My Club 0');
                $club->id = Uuid::fromString('84ac6e6d-4db8-4013-b078-5aac1827ba0e');
                $manager->persist($club);

                $userRepository = $manager->getRepository(User::class);
                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $joinRequest = new JoinRequest($club, $alice);
                $manager->persist($joinRequest);
                $joinRequest = new JoinRequest($club, $bob);
                $manager->persist($joinRequest);
                $manager->flush();
            }
        }, true);

        $userId = $this->findUser($I, BaseCest::ALICE_USER_EMAIL)->getId();
        $I->mockService(
            ElasticSearchClientBuilder::class,
            $I->mockElasticSearchClientBuilder()->findIdsByQuery(1, 'alice', [$userId])
        );

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);

        $clubId = '84ac6e6d-4db8-4013-b078-5aac1827ba0e';

        $I->sendGet('/v1/club/'.$clubId.'/join-requests?search=alice');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.response.items')[0]);
        $I->assertSame(
            BaseCest::ALICE_USER_NAME,
            $I->grabDataFromResponseByJsonPath('$.response.items')[0][0]['user']['name']
        );
    }

    private function findUser(ApiTester $I, string $email): User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $I->grabEntityFromRepository(User::class, [
            'email' => $email,
        ]);
    }
}
