<?php

namespace App\Event;

use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoRoom;
use Symfony\Contracts\EventDispatcher\Event;

class VideoRoomEvent extends Event
{
    const INITIATOR_JITSI = 'jitsi';
    const INITIATOR_TWILIO = 'twilio';

    public int $unixTimestamp;
    public string $initiator;
    public ?string $jitsiEndpointUuid = null;
    public VideoRoom $videoRoom;
    public ?VideoMeeting $videoMeeting = null;
    public ?User $user;
    public VideoRoomEventParameters $parameters;

    public function __construct(
        VideoRoom $videoRoom,
        ?VideoMeeting $videoMeeting,
        ?User $user,
        VideoRoomEventParameters $parameters,
        int $unixTimestamp,
        string $initiator,
        ?string $jitsiEndpointUuid = null
    ) {
        $this->videoRoom = $videoRoom;
        $this->videoMeeting = $videoMeeting;
        $this->user = $user;
        $this->parameters = $parameters;
        $this->unixTimestamp = $unixTimestamp;
        $this->initiator = $initiator;
        $this->jitsiEndpointUuid = $jitsiEndpointUuid;
    }

    public function getContext(): array
    {
        return [
            'unixTimestamp' => $this->unixTimestamp,
            'initiator' => $this->initiator,
            'jitsiEndpointUuid' => $this->jitsiEndpointUuid,
            'roomSid' => $this->parameters->roomSid,
            'roomName' => $this->parameters->roomName,
            'participantIdentity' => $this->parameters->participantIdentity,
        ];
    }
}
