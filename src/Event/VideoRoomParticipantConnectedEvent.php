<?php

namespace App\Event;

use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoRoom;

class VideoRoomParticipantConnectedEvent extends VideoRoomEvent
{
    public bool $endpointAllowIncomingMedia;

    public function __construct(
        bool $endpointAllowIncomingMedia,
        VideoRoom $videoRoom,
        ?VideoMeeting $videoMeeting,
        ?User $user,
        VideoRoomEventParameters $parameters,
        int $unixTimestamp,
        string $initiator,
        ?string $jitsiEndpointUuid = null
    ) {
        $this->endpointAllowIncomingMedia = $endpointAllowIncomingMedia;

        parent::__construct(
            $videoRoom,
            $videoMeeting,
            $user,
            $parameters,
            $unixTimestamp,
            $initiator,
            $jitsiEndpointUuid
        );
    }
}
