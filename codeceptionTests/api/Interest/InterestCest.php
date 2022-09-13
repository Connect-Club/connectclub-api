<?php

namespace App\Tests\Interest;

use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class InterestCest extends BaseCest
{
    const INTEREST_JSON_FORMAT = [
        'id' => 'integer',
        'name' => 'string',
    ];

    public function acceptanceTestInterests(ApiTester $I)
    {
        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                foreach ($manager->getRepository('App:Interest\Interest')->findAll() as $interest) {
                    $manager->remove($interest);
                }

                foreach ($manager->getRepository('App:Interest\InterestGroup')->findAll() as $group) {
                    $manager->remove($group);
                }

                $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $group = new InterestGroup('interest_group');
                $manager->persist($group);

                $user->addInterest(new Interest($group, 'Interest 1'));
                $user->addInterest(new Interest($group, 'Interest 2'));
                $user->addInterest(new Interest($group, 'Interest 3'));

                $group = new InterestGroup('interest_group');
                $manager->persist($group);

                $user->addInterest(new Interest($group, 'Interest 4'));
                $user->addInterest(new Interest($group, 'Interest 5'));
                $user->addInterest(new Interest($group, 'Interest 6'));

                foreach ($user->interests as $interest) {
                    $interest->isOld = false;
                    $manager->persist($interest);
                }

                $manager->persist($user);

                $manager->flush();
            }
        }, true);

        $interestJsonFormat = self::INTEREST_JSON_FORMAT;

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendGET('/v1/interests');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseMatchesJsonTypeStrict([
            $interestJsonFormat,
            $interestJsonFormat,
            $interestJsonFormat,
            $interestJsonFormat,
            $interestJsonFormat,
            $interestJsonFormat,
        ]);

        $I->seeResponseContainsJson([
            'response' => [
                ['name' => 'Interest 3'],
                ['name' => 'Interest 2'],
                ['name' => 'Interest 1'],
                ['name' => 'Interest 6'],
                ['name' => 'Interest 5'],
                ['name' => 'Interest 4']
            ],
        ]);
    }
}
