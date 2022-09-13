<?php

namespace App\Tests\VideoRoom;

use App\Controller\ErrorCode;
use App\Entity\Chat\AbstractChat;
use App\Entity\Chat\ChatParticipant;
use App\Entity\Community\CommunityParticipant;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\User;
use App\Event\VideoRoomEvent;
use App\Jabber\JabberClient;
use App\Service\JitsiEndpointManager;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Mockery;

class VideoRoomBanCest extends BaseCest
{
    public function tryBanUserWhichNotJoinInCommunity(ApiTester $I)
    {
        $mockJitsiEndpointManager = Mockery::mock(JitsiEndpointManager::class);
        $mockJitsiEndpointManager
            ->shouldReceive('disconnectUserFromRoom')
            ->never();
        $I->mockService(JitsiEndpointManager::class, $mockJitsiEndpointManager);

        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);
        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        $I->sendPOST('/v1/video-room/ban/'.self::VIDEO_ROOM_TEST_NAME.'/'.$aliceId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_COMMUNITY_PARTICIPANT_NOT_FOUND]]);
    }

    public function tryBanUserFromNotOwnedCommunity(ApiTester $I)
    {
        $mockJitsiEndpointManager = Mockery::mock(JitsiEndpointManager::class);
        $mockJitsiEndpointManager
            ->shouldReceive('disconnectUserFromRoom')
            ->never();
        $I->mockService(JitsiEndpointManager::class, $mockJitsiEndpointManager);

        $aliceId = $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]);
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPOST('/v1/video-room/ban/'.self::VIDEO_ROOM_TEST_NAME.'/'.$aliceId);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errors' => [ErrorCode::V1_ACCESS_DENIED]]);
    }

    public function ban(ApiTester $I)
    {
        $bob = $I->grabEntityFromRepository(User::class, ['email' => self::BOB_USER_EMAIL]);
        $bobId = $bob->id;

        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $videoRoom = $manager->getRepository('App:VideoChat\VideoRoom')
                                     ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);

                $manager->persist(new VideoMeeting($videoRoom, uniqid(), time(), VideoRoomEvent::INITIATOR_JITSI));
                $manager->persist(new VideoMeeting($videoRoom, uniqid(), time(), VideoRoomEvent::INITIATOR_TWILIO));

                $videoRoom->community->addParticipant($bob);

                $manager->persist($videoRoom->community);

                $manager->flush();
            }
        }, true);

        $mockJitsiEndpointManager = Mockery::mock(JitsiEndpointManager::class);
        $mockJitsiEndpointManager
            ->shouldReceive('disconnectUserFromRoom')
            ->times(2)
            ->with(
                Mockery::on(fn(User $u) => $u->id == $bobId),
                Mockery::on(fn(VideoRoom $v) => $v->community->name == self::VIDEO_ROOM_TEST_NAME)
            );
        $I->mockService(JitsiEndpointManager::class, $mockJitsiEndpointManager);

        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
        /** @var VideoRoom $videoRoom */
        $videoRoom = $I->grabEntityFromRepository(VideoRoom::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_TEST_NAME
            ]
        ]);
        $password = $videoRoom->community->password;

        $I->seeInRepository(CommunityParticipant::class, [
            'community' => [
                'name' => self::VIDEO_ROOM_TEST_NAME,
            ],
            'user' => [
                'id' => $bobId
            ]
        ]);

        //Ban users
        $I->sendPOST('/v1/video-room/ban/'.self::VIDEO_ROOM_TEST_NAME.'/'.$bobId);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->loadFixtures(new class extends AbstractFixture {
            public function load(ObjectManager $manager)
            {
                $videoRoom = $manager->getRepository('App:VideoChat\VideoRoom')
                    ->findOneByName(BaseCest::VIDEO_ROOM_TEST_NAME);

                $bob = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
                $videoRoom->community->addParticipant($bob);

                foreach ($manager->getRepository('App:VideoChat\VideoRoomBan')->findAll() as $ban) {
                    $manager->remove($ban);
                }

                $meetings = $manager->getRepository('App:VideoChat\VideoMeeting')->findBy(['videoRoom' => $videoRoom]);
                foreach ($meetings as $meeting) {
                    $manager->remove($meeting);
                }

                $manager->persist($videoRoom->community);
                $manager->persist(new VideoMeeting($videoRoom, uniqid(), time(), VideoRoomEvent::INITIATOR_TWILIO));

                $manager->flush();
            }
        }, true);

        //Ban users
        $I->sendPOST('/v1/video-room/ban/'.self::VIDEO_ROOM_TEST_NAME.'/'.$bobId);
        $I->seeResponseCodeIs(HttpCode::OK);

        //Try join again
        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPOST('/v2/video-room/token/'.self::VIDEO_ROOM_TEST_NAME, json_encode([
            'password' => $password
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson([
            'response' => null,
            'errors' => [
                ErrorCode::V1_VIDEO_ROOM_JOIN_USER_BANNED
            ]
        ]);

        $I->amBearerAuthenticated(self::BOB_ACCESS_TOKEN);
        $I->sendPOST('/v2/video-room/token/'.self::VIDEO_ROOM_TEST_NAME, json_encode([
            'password' => $password
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson([
            'response' => null,
            'errors' => [
                ErrorCode::V1_VIDEO_ROOM_JOIN_USER_BANNED
            ]
        ]);
    }
}
