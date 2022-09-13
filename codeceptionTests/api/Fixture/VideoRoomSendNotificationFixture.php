<?php

namespace App\Tests\Fixture;

use App\DataFixtures\AccessTokenFixture;
use App\DataFixtures\VideoRoomFixture;
use App\Entity\Community\CommunityParticipant;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Tests\BaseCest;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class VideoRoomSendNotificationFixture extends AbstractFixture implements DependentFixtureInterface
{
    private string $sid;
    private int $countUsers;
    private bool $saveUsersAsMeetingParticipant;

    public function __construct(string $sid, int $countUsers, bool $saveUsersAsMeetingParticipant = true)
    {
        $this->sid = $sid;
        $this->countUsers = $countUsers;
        $this->saveUsersAsMeetingParticipant = $saveUsersAsMeetingParticipant;
    }

    public function load(ObjectManager $manager)
    {
        $videoRoom = $manager->getRepository('App:Community\Community')
                             ->findOneBy(['name' => BaseCest::VIDEO_ROOM_TEST_NAME])
                             ->videoRoom;

        $meeting = new VideoMeeting($videoRoom, $this->sid, time());
        $manager->persist($meeting);

        $community = $manager->getRepository('App:Community\Community')
            ->findOneBy(['name' => BaseCest::VIDEO_ROOM_TEST_NAME]);

        $manager->persist($community->videoRoom);
        $manager->flush();

        foreach ($meeting->participants as $participant) {
            $manager->remove($participant);
        }

        for ($i = 0; $i < $this->countUsers; $i++) {
            $user = new User();
            $user->email = 'email-'.$i.'@test.ru';
            $user->name = 'Name-'.$i;
            $user->surname = 'Surname-'.$i;

            $manager->persist($user);
            $manager->persist(new User\Device(
                Uuid::uuid4()->toString(),
                $user,
                User\Device::TYPE_ANDROID,
                Uuid::uuid4()->toString(),
                'Europe/Moscow',
                'ru'
            ));
            $manager->persist(new User\Device(
                Uuid::uuid4()->toString(),
                $user,
                User\Device::TYPE_IOS,
                Uuid::uuid4()->toString(),
                'Europe/Moscow',
                'ru'
            ));

            if ($this->saveUsersAsMeetingParticipant) {
                $participant = new VideoMeetingParticipant($meeting, $user, time());
                $manager->persist($participant);
                $meeting->participants->add($participant);
            }

            $manager->persist(new CommunityParticipant($user, $meeting->videoRoom->community));
        }

        $manager->persist($meeting);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [VideoRoomFixture::class, AccessTokenFixture::class];
    }
}
