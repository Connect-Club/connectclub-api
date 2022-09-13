<?php

namespace App\Tests\Fixture;

use App\Entity\Community\Community;
use App\Entity\User;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CallVideoRoomFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $community = $manager->getRepository(Community::class)->findOneBy([
            'name' => BaseCest::VIDEO_ROOM_TEST_NAME
        ]);
        $community->videoRoom->isPrivate = true;

        $bob = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);

        $community->addParticipant($bob);

        $community->videoRoom->addInvitedUser($community->owner);
        $community->videoRoom->addInvitedUser($bob);

        $manager->flush();
    }
}
