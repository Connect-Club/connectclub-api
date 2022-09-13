<?php

namespace App\Tests\Event;

use App\Entity\Community\CommunityParticipant;
use App\Entity\User;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EventPromoteCest extends BaseCest
{
    public function testPromoteDemote(ApiTester $I)
    {
        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);
        $bobId = $I->grabFromRepository(User::class, 'id', ['email' => self::BOB_USER_EMAIL]);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

                $community = $manager->getRepository('App:Community\Community')->findOneBy([
                    'name' => BaseCest::VIDEO_ROOM_TEST_NAME
                ]);

                $community->addParticipant($alice);
                $community->addParticipant($bob);

                $manager->persist($community);
                $manager->flush();
            }
        }, true);

        $I->sendPost('/v1/event/'.self::VIDEO_ROOM_TEST_NAME.'/'.$aliceId.'/promote');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(CommunityParticipant::class, [
            'community' => ['name' => self::VIDEO_ROOM_TEST_NAME],
            'user' => ['email' => self::ALICE_USER_EMAIL],
            'role' => CommunityParticipant::ROLE_MODERATOR,
        ]);

        $I->sendPost('/v1/event/'.self::VIDEO_ROOM_TEST_NAME.'/'.$bobId.'/promote');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(CommunityParticipant::class, [
            'community' => ['name' => self::VIDEO_ROOM_TEST_NAME],
            'user' => ['email' => self::BOB_USER_EMAIL],
            'role' => CommunityParticipant::ROLE_MODERATOR,
        ]);

        $I->sendPost('/v1/event/'.self::VIDEO_ROOM_TEST_NAME.'/'.$bobId.'/demote');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInRepository(CommunityParticipant::class, [
            'community' => ['name' => self::VIDEO_ROOM_TEST_NAME],
            'user' => ['email' => self::BOB_USER_EMAIL],
            'role' => CommunityParticipant::ROLE_MEMBER,
        ]);

        //Cannot close event
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPost('/v1/event/'.self::VIDEO_ROOM_TEST_NAME.'/close');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        //Can close event as moderator
        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
        $I->sendPost('/v1/event/'.self::VIDEO_ROOM_TEST_NAME.'/close');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
}
