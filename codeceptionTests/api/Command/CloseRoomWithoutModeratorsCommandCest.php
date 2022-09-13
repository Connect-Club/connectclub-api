<?php

namespace App\Tests\Command;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityParticipant;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Service\JitsiEndpointManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Symfony\Component\Console\Tester\CommandTester;
use App\Kernel;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Symfony\Bundle\FrameworkBundle\Console\Application;

class CloseRoomWithoutModeratorsCommandCest extends BaseCest
{
    public function testDisableVideoRoomWithoutOnlineModerators(ApiTester $I)
    {
        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
                $alice = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $mike = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);

                $community = new Community($main, 'VideoRoom', 'Cool video room without moderators');
                $community->addParticipant($alice, CommunityParticipant::ROLE_ADMIN);
                $community->addParticipant($bob, CommunityParticipant::ROLE_MODERATOR);
                $community->addParticipant($mike);

                $community->videoRoom->startedAt = time();
                $community->videoRoom->isPrivate = false;

                $meeting = new VideoMeeting($community->videoRoom, uniqid(), time());
                $aliceParticipant = new VideoMeetingParticipant($meeting, $alice, time());
                $aliceParticipant->endTime = time() - 125;
                $mainParticipant = new VideoMeetingParticipant($meeting, $main, time());
                $mainParticipant->endTime = time() - 125;

                $meeting->participants->add($aliceParticipant);
                $meeting->participants->add($mainParticipant);
                $meeting->participants->add(new VideoMeetingParticipant($meeting, $mike, time()));

                $community->videoRoom->meetings->add($meeting);

                $manager->persist($community);
                $manager->flush();
            }
        });

        /** @var Kernel $kernel */
        $kernel = $I->grabService('kernel');
        $application = new Application($kernel);

        $mockJitsiEndpointManager = Mockery::mock(JitsiEndpointManager::class);
        $mockJitsiEndpointManager
            ->shouldReceive('disconnectUserFromRoom')
            ->with(
                Mockery::on(fn(User $u) => $u->email == BaseCest::MIKE_USER_EMAIL),
                Mockery::on(fn(VideoRoom $room) => $room->community->name == 'VideoRoom')
            )
            ->once();
        $I->mockService(JitsiEndpointManager::class, $mockJitsiEndpointManager);

        $command = $application->find('CloseRoomWithoutModeratorsCommand');

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $community = $I->grabEntityFromRepository(Community::class, ['name' => 'VideoRoom']);
        $I->assertNotNull($community->videoRoom->doneAt);
    }
}
