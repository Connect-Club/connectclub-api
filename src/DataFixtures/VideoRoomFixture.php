<?php

namespace App\DataFixtures;

use App\Entity\Community\Community;
use App\Entity\VideoChat\BackgroundPhoto;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\Object\VideoRoomPortalObject;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoomConfig;
use App\Entity\VideoChat\VideoRoomQuality;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VideoRoomFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $quality = new VideoRoomQuality(480, 360);

        $background = new BackgroundPhoto(
            'default',
            'src_111',
            'src_111',
            200,
            300,
            $this->getReference('user-test')
        );
        $manager->persist($background);

        $communityTestUser = new Community(
            $this->getReference('user-test'),
            BaseCest::VIDEO_ROOM_TEST_NAME
        );
        $communityTestUser->description = 'Video room description';
        $communityTestUser->videoRoom->matchingEnabled = true;
        $communityTestUser->videoRoom->config = new VideoRoomConfig(2, 2, 1, 1, 3, 480, 3000, 300, $quality);
        $communityTestUser->videoRoom->config->backgroundRoom = $background;

        $communityBobUser = new Community(
            $this->getReference('bob-test'),
            BaseCest::VIDEO_ROOM_BOB_NAME
        );
        $communityBobUser->videoRoom->matchingEnabled = true;
        $communityBobUser->videoRoom->config = new VideoRoomConfig(2, 2, 1, 1, 3, 480, 3000, 300, $quality);
        $communityBobUser->videoRoom->config->backgroundRoom = $background;

        $usersVariationVariants = [
            ['bob-test', 'alice-test', 'user-test'],
            ['bob-test', 'alice-test'],
            ['bob-test'],
        ];

        for ($i = 0; $i < 20; ++$i) {
            $meeting = new VideoMeeting($communityBobUser->videoRoom, uniqid(), 1587546633 + $i * 3600);
            $manager->persist($meeting);

            $usersVariationVariant = $usersVariationVariants[$i % 3];
            foreach ($usersVariationVariant as $variantUser) {
                $startTime = time() - 1587546633 + $i * 60;
                $participant = new VideoMeetingParticipant(
                    $meeting,
                    $this->getReference($variantUser),
                    $startTime
                );
                $participant->endTime = $startTime + 3600 * $i;
                $manager->persist($participant);
            }
        }

        $object = new VideoRoomPortalObject(
            null,
            $communityBobUser->videoRoom->config->backgroundRoom,
            new Location(),
            200,
            300,
            'portal',
            'qwerty'
        );

        $manager->persist($object);
        $manager->persist($communityBobUser);
        $manager->persist($communityTestUser);

        $manager->flush();

        $this->setReference(BaseCest::VIDEO_ROOM_BOB_NAME, $communityBobUser->videoRoom);
    }

    public function getDependencies()
    {
        return [
            UserFixture::class,
        ];
    }
}
