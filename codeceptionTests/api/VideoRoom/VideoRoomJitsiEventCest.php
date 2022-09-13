<?php

namespace App\Tests\VideoRoom;

use Anboo\RabbitmqBundle\AMQP\Producer;
use App\Service\Amplitude\AmplitudeManager;
use App\Tests\Fixture\VideoRoomSendNotificationFixture;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Collections\Criteria;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Mockery;
use App\Entity\Chat\ChatAccess;
use App\Entity\Chat\ChatParticipant;
use App\Entity\Chat\ChatSettings;
use App\Entity\Chat\GroupChat;
use App\Entity\Community\Community;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Jabber\JabberClient;
use App\Message\SendNotificationMessage;
use App\Message\SendStanzaMessage;
use App\Message\SendUserJoinedGroupChatAnnounce;
use App\Service\SlackClient;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class VideoRoomJitsiEventCest extends BaseCest
{
    const ROOM_SID = 'b7edb0745c6';
    const GROUP_CHAT_NAME = 'group_chat_video_room_video_room_name_5f5107b57659d';

    public function eventRoomParticipantEvents(ApiTester $I)
    {
        $mockProducer = Mockery::mock(Producer::class);
        $mockProducer->shouldReceive('publishToExchange');
        $I->mockService(Producer::class, $mockProducer);

        $busMock = Mockery::spy(AmplitudeManager::class);
        $I->mockService(AmplitudeManager::class, $busMock);

        $url = '/v2/video-room/event';

        //Event room created
        $I->sendPOST($url, $this->generateBody('CONFERENCE_CREATED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $videoMeetings = $I->grabEntitiesFromRepository(VideoMeeting::class, ['sid' => self::ROOM_SID]);
        $I->assertCount(1, $videoMeetings);
        $I->assertEquals(1, $videoMeetings[0]->jitsiCounter);

        $I->sendPOST($url, $this->generateBody('CONFERENCE_CREATED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $videoMeetings2 = $I->grabEntitiesFromRepository(VideoMeeting::class, ['sid' => self::ROOM_SID]);
        $I->assertCount(1, $videoMeetings2);
        $I->assertEquals($videoMeetings[0]->id, $videoMeetings2[0]->id);
        $I->assertEquals(2, $videoMeetings2[0]->jitsiCounter);

        /** @var User[] $users */
        $users = $I->grabEntitiesFromRepository(User::class, [
            Criteria::expr()->neq('email', 'mike@test.ru')
        ]);
        usort($users, fn (User $a, User $b) => $a->id > $b->id);

        $usersEndpointsUuid = [];
        foreach ($users as $user) {
            $usersEndpointsUuid[$user->id] = Uuid::uuid4()->toString();
        }

        foreach (array_slice($users, 0, 3) as $user) {
            $uuid = $usersEndpointsUuid[$user->id];

            //Event participant connected
            $I->sendPOST(
                $url,
                $this->generateBody('ENDPOINT_CREATED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID, $user->id, $uuid)
            );
            $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
            $I->seeInRepository(VideoMeetingParticipant::class, [
                'participant' => ['id' => $user->id,],
                'videoMeeting' => ['sid' => self::ROOM_SID]
            ]);

            Mockery::close();
        }

        foreach (array_slice($users, 0, 3) as $user) {
            $uuid = $usersEndpointsUuid[$user->id];

            //Event participant disconnected
            $I->sendPOST(
                $url,
                $this->generateBody('ENDPOINT_EXPIRED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID, $user->id, $uuid)
            );

            //Event participant disconnected from another node jitsi
            $I->sendPOST(
                $url,
                $this->generateBody('ENDPOINT_EXPIRED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID, $user->id, $uuid)
            );
            
            $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
            $I->seeInRepository(VideoMeetingParticipant::class, [
                'participant' => ['id' => $user->id],
                'videoMeeting' => ['sid' => self::ROOM_SID]
            ]);

            Mockery::close();
        }

        foreach ($users as $user) {
            $uuid = $usersEndpointsUuid[$user->id];

            //Event participant connected
            $I->sendPOST(
                $url,
                $this->generateBody('ENDPOINT_CREATED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID, $user->id, $uuid)
            );

            //Event participant connected
            $I->sendPOST(
                $url,
                $this->generateBody('ENDPOINT_CREATED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID, $user->id, $uuid)
            );

            $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
            $participants = $I->grabEntitiesFromRepository(VideoMeetingParticipant::class, [
                'participant' => ['id' => $user->id],
                'videoMeeting' => ['sid' => self::ROOM_SID],
                'endTime' => null,
                'jitsiEndpointUuid' => $uuid,
            ]);

            $I->assertCount(1, $participants);

            Mockery::close();
        }

        $mock = Mockery::mock(SlackClient::class);
        $mock->shouldReceive('sendMessageWithThread')->once();
        $I->mockService(SlackClient::class, $mock);

//        //Event room ended
        $I->sendPOST($url, $this->generateBody('CONFERENCE_EXPIRED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID));
        $meetings = $I->grabEntitiesFromRepository(VideoMeeting::class, ['sid' => self::ROOM_SID]);
        $I->assertCount(1, $meetings);
        $I->assertEquals(1, $meetings[0]->jitsiCounter);
        $I->assertNull($meetings[0]->endTime);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);

        $I->sendPOST($url, $this->generateBody('CONFERENCE_EXPIRED', self::VIDEO_ROOM_TEST_NAME, self::ROOM_SID));
        $meetings = $I->grabEntitiesFromRepository(VideoMeeting::class, ['sid' => self::ROOM_SID]);
        $I->assertCount(1, $meetings);
        $I->assertEquals(0, $meetings[0]->jitsiCounter);
        $I->assertNotNull($meetings[0]->endTime);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
    }

    private function generateBody(
        string $eventType,
        string $conferenceGid,
        string $conferenceId,
        ?int $endpointId = null,
        ?string $endpointUuid = null
    ): string {
        return json_encode(array_filter([
            'eventType' => $eventType,
            'conferenceGid' => $conferenceGid,
            'conferenceId' => $conferenceId,
            'endpointId' => (string) $endpointId,
            'endpointUuid' => $endpointUuid,
        ], fn($value) => !is_null($value)));
    }
}
