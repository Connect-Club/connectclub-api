<?php


namespace App\Tests\VideoRoom;

use App\Entity\VideoChat\VideoRoom;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Util\HttpCode;
use Ramsey\Uuid\Uuid;

class BaseJitsiCest extends BaseCest
{
    protected function createMeetingForVideoRoom(ApiTester $I, VideoRoom $videoRoom): string
    {
        $sid = uniqid();
        $I->sendPost('/v2/video-room/event', json_encode([
            'eventType' => 'CONFERENCE_CREATED',
            'conferenceGid' => $videoRoom->community->name,
            'conferenceId' => $sid,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        return $sid;
    }

    protected function closeMeetingForVideoRoom(ApiTester $I, VideoRoom $videoRoom, string $sid): void
    {
        $I->sendPost('/v2/video-room/event', json_encode([
            'eventType' => 'CONFERENCE_EXPIRED',
            'conferenceGid' => $videoRoom->community->name,
            'conferenceId' => $sid,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    protected function createEndpoint(ApiTester $I, VideoRoom $videoRoom, string $sid, int $endpointId): string
    {
        $endpointUuid = Uuid::uuid4()->toString();

        $I->sendPost('/v2/video-room/event', json_encode([
            'eventType' => 'ENDPOINT_CREATED',
            'conferenceGid' => $videoRoom->community->name,
            'conferenceId' => $sid,
            'endpointId' => (string) $endpointId,
            'endpointUuid' => $endpointUuid,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        return $endpointUuid;
    }

    protected function closeEndpoint(
        ApiTester $I,
        VideoRoom $videoRoom,
        string $sid,
        int $endpointId,
        string $endpointUuid
    ): void {
        $I->sendPost('/v2/video-room/event', json_encode([
            'eventType' => 'ENDPOINT_CREATED',
            'conferenceGid' => $videoRoom->community->name,
            'conferenceId' => $sid,
            'endpointId' => (string) $endpointId,
            'endpointUuid' => Uuid::uuid4()->toString(),
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
