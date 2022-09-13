<?php


namespace App\Tests\VideoRoom;

use App\Entity\Club\Club;
use App\Entity\Community\Community;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Message\AmplitudeEventStatisticsMessage;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OnRoomEndedUnlockToggleClubCest extends BaseJitsiCest
{
    const COMMUNITY_MAIN_NAME = 'videoroommaintest';

    public function test(ApiTester $I)
    {
        $mock = Mockery::spy(MessageBusInterface::class);
        $mock->shouldReceive('dispatch')
             ->andReturn(new Envelope(Mockery::mock(AmplitudeEventStatisticsMessage::class)));
        $I->mockService(MessageBusInterface::class, $mock);

        $I->loadFixtures(new class extends Fixture {
            public function load(ObjectManager $manager)
            {
                $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);

                $club = new Club($main, 'Main club toggle test');
                $eventSchedule = new EventSchedule($main, 'Event schedule', time(), null);
                $eventSchedule->club = $club;

                $manager->persist($club);
                $manager->persist($eventSchedule);

                $community = new Community($main, OnRoomEndedUnlockToggleClubCest::COMMUNITY_MAIN_NAME);
                $community->videoRoom->type = VideoRoom::TYPE_NEW;
                $community->videoRoom->eventSchedule = $eventSchedule;

                $manager->persist($community);
                $manager->flush();
            }
        }, true);

        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::COMMUNITY_MAIN_NAME
            ]
        ]);

        $mainId = $I->grabFromRepository(User::class, 'id', ['email' => self::MAIN_USER_EMAIL]);
        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);

        $sid = $this->createMeetingForVideoRoom($I, $videoRoom);
        $endpointUuid = $this->createEndpoint($I, $videoRoom, $sid, $mainId);
        $this->closeEndpoint($I, $videoRoom, $sid, $mainId, $endpointUuid);
        $this->closeMeetingForVideoRoom($I, $videoRoom, $sid);

        /** @var Club $club */
        $club = $I->grabEntityFromRepository(Club::class, ['title' => 'Main club toggle test']);
        $I->assertEquals(false, $club->togglePublicModeEnabled);

        $sid = $this->createMeetingForVideoRoom($I, $videoRoom);
        $endpointUuid = $this->createEndpoint($I, $videoRoom, $sid, $mainId);
        $this->closeEndpoint($I, $videoRoom, $sid, $mainId, $endpointUuid);
        $endpointUuid = $this->createEndpoint($I, $videoRoom, $sid, $aliceId);
        $this->closeEndpoint($I, $videoRoom, $sid, $aliceId, $endpointUuid);
        $this->closeMeetingForVideoRoom($I, $videoRoom, $sid);

        /** @var Club $club */
        $club = $I->grabEntityFromRepository(Club::class, ['title' => 'Main club toggle test']);
        $I->assertEquals(true, $club->togglePublicModeEnabled);
    }
}
