<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CommunityDraftFixture extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [UserFixture::class];
    }

    public function load(ObjectManager $manager)
    {
        $userTest = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

        for ($i = 0; $i < 3; ++$i) {
            $background = new BackgroundPhoto('default', 'src_'.$i, 'src_'.$i, 200, 300, $userTest);
            $manager->persist($background);

            $draft = new \App\Entity\Community\CommunityDraft(
                'community_draft'.$i,
                $background,
                200,
                100
            );
            $manager->persist($draft);
        }

        $manager->flush();
    }
}
