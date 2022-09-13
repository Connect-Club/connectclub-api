<?php

namespace App\Service\Notification\Message;

use App\Entity\VideoChat\VideoRoom;
use App\Service\Notification\Push\ReactNativePushNotification;

class ReactNativeVideoRoomNotification extends ReactNativePushNotification
{
    public function __construct(
        VideoRoom $videoRoom,
        ?string $title,
        ?string $message,
        array $options = [],
        string $type = null,
        array $predefinedTranslationParameters = []
    ) {
        $options['videoRoomId'] = $videoRoom->community->name;
        $options['videoRoomPassword'] = $videoRoom->community->password;

        if ($videoRoom->eventSchedule && $videoRoom->eventSchedule->club) {
            $options['clubTitle'] = $videoRoom->eventSchedule->club->title;
        }

        parent::__construct($type ?? 'video-room', $title, $message, $options, $predefinedTranslationParameters);
    }
}
