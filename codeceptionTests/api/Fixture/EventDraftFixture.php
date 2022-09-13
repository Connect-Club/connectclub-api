<?php

namespace App\Tests\Fixture;

use App\Entity\Event\EventDraft;
use App\Entity\Follow\Follow;
use App\Entity\User;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class EventDraftFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $userRepository = $manager->getRepository(User::class);

        $user = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
        $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);

        $manager->persist(new Follow($user, $alice));
        $manager->persist(new Follow($alice, $user));

        $photo = new BackgroundPhoto('default', 'original.png', 'src.png', 100, 200, $user);
        $manager->persist($photo);

        $draftFixture = new EventDraft(
            EventDraft::TYPE_SMALL_BROADCASTING,
            '',
            $photo,
            1,
            0,
            true,
            0,
            10
        );
        $draftFixture->id = Uuid::fromString('b13d048e-e594-49f6-a932-efdf02853335');

        $manager->persist($draftFixture);
        $manager->flush();
    }
}
