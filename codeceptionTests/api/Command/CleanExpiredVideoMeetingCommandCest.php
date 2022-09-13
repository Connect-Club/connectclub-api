<?php

namespace App\Tests\Command;

use App\ConnectClub;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;
use App\Kernel;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Symfony\Bundle\FrameworkBundle\Console\Application;

class CleanExpiredVideoMeetingCommandCest extends BaseCest
{
    public function testExecuteEmpty(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $community = $manager->getRepository('App:Community\Community')->findOneBy([
                    'name' => BaseCest::VIDEO_ROOM_TEST_NAME
                ]);

                $videoRoom = $community->videoRoom;

                $manager->persist(new VideoMeeting(
                    $videoRoom,
                    'first_meeting',
                    time() - ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT - 1
                ));

                $secondMeeting = new VideoMeeting(
                    $videoRoom,
                    'second_meeting',
                    time() - ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT - 1
                );
                for ($i = 0; $i < 10; $i++) {
                    if ($i % 3) {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                    } elseif ($i % 2) {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                    } else {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                    }

                    $participant = new VideoMeetingParticipant(
                        $secondMeeting,
                        $user,
                        time(),
                        Uuid::uuid4()->toString()
                    );

                    $manager->persist($participant);
                }
                $manager->persist($secondMeeting);

                $thirdMeeting = new VideoMeeting(
                    $videoRoom,
                    'third_meeting',
                    time() - ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT - 1
                );
                for ($i = 0; $i < 10; $i++) {
                    if ($i % 3) {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                    } elseif ($i % 2) {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                    } else {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                    }

                    $participant = new VideoMeetingParticipant(
                        $thirdMeeting,
                        $user,
                        time(),
                        Uuid::uuid4()->toString()
                    );
                    $participant->endTime = time() + mt_rand(0, 1000);

                    $manager->persist($participant);
                }
                $manager->persist($thirdMeeting);

                $meetingWithParticipantsAndEmptyJitsi = new VideoMeeting(
                    $videoRoom,
                    'meeting_with_participants_and_empty_jitsi',
                    time() - ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT - 1
                );

                for ($i = 0; $i < 10; $i++) {
                    if ($i % 3) {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                    } elseif ($i % 2) {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                    } else {
                        $user = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                    }

                    $participant = new VideoMeetingParticipant(
                        $meetingWithParticipantsAndEmptyJitsi,
                        $user,
                        time(),
                        Uuid::uuid4()->toString()
                    );

                    $manager->persist($participant);
                }

                $meetingWithParticipantsAndEmptyJitsi->isEmptyMeeting = false;
                $manager->persist($meetingWithParticipantsAndEmptyJitsi);

                $manager->flush();
            }
        }, true);

        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);
        $command = $application->find('CleanExpiredVideoMeetingCommand');
        $commandTester = new CommandTester($command);

        $I->assertNull(
            $I->grabEntityFromRepository(VideoMeeting::class, ['sid' => 'first_meeting'])->endTime
        );
        $I->assertNull(
            $I->grabEntityFromRepository(VideoMeeting::class, ['sid' => 'second_meeting'])->endTime
        );
        $I->assertNull(
            $I->grabEntityFromRepository(VideoMeeting::class, ['sid' => 'third_meeting'])->endTime
        );
        $I->assertNull(
            $I->grabEntityFromRepository(
                VideoMeeting::class,
                ['sid' => 'meeting_with_participants_and_empty_jitsi']
            )->endTime
        );

        $commandTester->execute([]);

        $I->assertNotNull(
            $I->grabEntityFromRepository(VideoMeeting::class, ['sid' => 'first_meeting'])->endTime
        );
        $I->assertNull(
            $I->grabEntityFromRepository(VideoMeeting::class, ['sid' => 'second_meeting'])->endTime
        );
        $I->assertNotNull(
            $I->grabEntityFromRepository(VideoMeeting::class, ['sid' => 'third_meeting'])->endTime
        );
        $I->assertNull(
            $I->grabEntityFromRepository(
                VideoMeeting::class,
                ['sid' => 'meeting_with_participants_and_empty_jitsi']
            )->endTime
        );
    }
}
