<?php

namespace App\DataFixtures;

use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\User\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class InterestFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $interestGroupA = new InterestGroup('🔥 Hustle');
        $interestGroupA->isOld = true;
        $manager->persist(new Interest($interestGroupA, '🎯 Pitch Practice', 0));
        $manager->persist(new Interest($interestGroupA, '🌱 Networking', 0));
        $manager->persist(new Interest($interestGroupA, '🎵 TikTok', 0));
        $manager->persist(new Interest($interestGroupA, '🏠 Real Estate', 1));
        $manager->persist(new Interest($interestGroupA, '🌈 Instagram', 1));
        $manager->persist(new Interest($interestGroupA, '📷 Photography', 1));

        $interestGroupB = new InterestGroup('🎬 Arts');
        $interestGroupB->globalSort = 1;
        $interestGroupB->isOld = true;
        $manager->persist(new Interest($interestGroupB, '📖 Writing', 0, false, 1));
        $manager->persist(new Interest($interestGroupB, '📷 Photography', 0, false, 2));
        $manager->persist(new Interest($interestGroupB, '🔥 Burning Man', 0, false, 3));
        $manager->persist(new Interest($interestGroupB, '🍔 Food and Drink', 1, false, 4));
        $manager->persist(new Interest($interestGroupB, '🎨 Design', 1, false, 5));

        $interestGroupC = new InterestGroup('💬 Languages');
        $interestGroupC->isOld = true;
        $en = new Language('🇬🇧 English', 'EN');
        $ge = new Language('🇩🇪 German', 'GE');
        $ru = new Language('🇷🇺 Russian', 'RU');

        $manager->persist($ge);
        $manager->persist($ru);
        $manager->persist($en);

        $manager->persist($interestGroupA);
        $manager->persist($interestGroupB);
        $manager->persist($interestGroupC);
        $manager->flush();

        $manager->refresh($interestGroupA);
        $manager->refresh($interestGroupB);

        foreach ($interestGroupA->interests as $interest) {
            $interest->isOld = false;
            $manager->persist($interest);
        }

        foreach ($interestGroupB->interests as $interest) {
            $interest->isOld = false;
            $manager->persist($interest);
        }

        $manager->flush();
    }
}
