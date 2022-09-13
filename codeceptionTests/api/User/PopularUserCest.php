<?php

namespace App\Tests\User;

use App\Entity\Invite\Invite;
use App\Entity\User;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;

class PopularUserCest
{
    public function testMostPopularByInvites(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $mike->state = User::STATE_VERIFIED;
                $manager->persist($mike);

                $util = PhoneNumberUtil::getInstance();

                foreach ([$main, $alice, $bob, $mike] as $k => $author) {
                    for ($i = 0; $i < $k; $i++) {
                        $phoneNumber = $util->parse('+796364176'.$k.$i);

                        $user = new User();
                        $user->name = 'Name-'.$k.$i;
                        $user->surname = 'Surname-'.$k.$i;
                        $user->phone = $phoneNumber;
                        $user->username = 'username-'.$k.$i;
                        $user->email = 'user-'.$i.'@test.ru';
                        $user->state = User::STATE_VERIFIED;
                        $manager->persist($user);

                        $invite = new Invite($author, $phoneNumber);
                        $invite->registeredUser = $user;

                        $manager->persist($invite);
                    }
                }

                $manager->flush();
            }
        });

        $I->sendGet('/v1/popular/inviters');
        $I->seeResponseContainsJson([
            'response' => [
                0 => [
                    'name' => 'Mike',
                    'surname' => 'Mike',
                    'count' => 6,
                ],
                1 => [
                    'name' => 'bob_user_name',
                    'surname' => 'bob_user_surname',
                    'count' => 2,
                ],
                2 => [
                    'name' => 'alice_user_name',
                    'surname' => 'alice_user_surname',
                    'count' => 1,
                ],
            ],
        ]);
    }
}
