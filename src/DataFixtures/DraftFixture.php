<?php

namespace App\DataFixtures;

use App\Entity\Event\EventDraft;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\VideoRoomDraft;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DraftFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $userTest = $this->getReference('user-test');

        for ($i = 0; $i < 3; ++$i) {
            $background = new BackgroundPhoto('default', 'src_'.$i, 'src_'.$i, 200, 300, $userTest);
            $manager->persist($background);
            $draft = new VideoRoomDraft(
                'draft_video_room_'.$i,
                $background,
                200,
                100
            );
            $manager->persist($draft);
        }

        $background = new BackgroundPhoto('default', 'src_'.$i, 'src_'.$i, 200, 300, $userTest);
        $manager->persist($background);

        $manager->persist(new EventDraft(
            EventDraft::TYPE_PRIVATE,
            'draft_video_room_'.$i,
            $background,
            200,
            100,
            false,
            10,
            10
        ));

        $manager->persist(new EventDraft(
            EventDraft::TYPE_SMALL_BROADCASTING,
            'draft_video_room_'.$i,
            $background,
            200,
            100,
            true,
            10,
            10
        ));

        $manager->flush();
    }
}
