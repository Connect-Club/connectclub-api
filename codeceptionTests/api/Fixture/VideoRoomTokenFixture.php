<?php

namespace App\Tests\Fixture;

use App\DataFixtures\AccessTokenFixture;
use App\DataFixtures\VideoRoomFixture;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Tests\BaseCest;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VideoRoomTokenFixture extends AbstractFixture implements DependentFixtureInterface
{
    private string $mainMeetingSid;
    private int $countOnlineUsers;
    private int $countOfflineUsers;

    public function __construct(string $mainMeetingSid, int $countOnlineUsers, int $countOfflineUsers)
    {
        $this->mainMeetingSid = $mainMeetingSid;
        $this->countOnlineUsers = $countOnlineUsers;
        $this->countOfflineUsers = $countOfflineUsers;
    }

    public function getDependencies()
    {
        return [VideoRoomFixture::class, AccessTokenFixture::class,];
    }

    public function load(ObjectManager $manager)
    {
        $countUsers = $this->countOfflineUsers + $this->countOnlineUsers;

        for ($i = 0; $i < $countUsers; $i++) {
            $user = new User();
            $user->email = 'test'.$i.'@test.ru';
            $manager->persist($user);
            $this->setReference('user-'.$i, $user);
        }
        $manager->flush();

        /** @var VideoRoom $videoRoomBob */
        $videoRoomBob = $this->getReference(BaseCest::VIDEO_ROOM_BOB_NAME);
        foreach ($videoRoomBob->meetings as $meeting) {
            $manager->remove($meeting);
        }
        $manager->flush();

        $meeting = new VideoMeeting($videoRoomBob, $this->mainMeetingSid, time());
        $manager->persist($meeting);

        for ($i = 0; $i < $countUsers; $i++) {
            $participant = new VideoMeetingParticipant(
                $meeting,
                $this->getReference('user-'.$i),
                time()
            );

            if ($i >= $this->countOnlineUsers) {
                $participant->endTime = time() + $i;
            }

            $manager->persist($participant);
        }

        $manager->flush();
    }
}
