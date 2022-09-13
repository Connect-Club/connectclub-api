<?php

namespace App\Service\Notification\Message;

use App\Entity\Event\EventScheduleParticipant;
use App\Entity\User;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\Notification\TimeSpecificZoneTranslationParameter;
use App\Util\TimeZone;

class ReactNativeRegisteredAsCoHostNotification extends ReactNativePushNotification
{
    public function __construct(
        User $initiator,
        EventScheduleParticipant $eventScheduleParticipant,
        array $options = []
    ) {
        $club = $eventScheduleParticipant->event->club;

        parent::__construct(
            'event-schedule',
            'notifications.club_registered_as_co_host_title',
            $club ? 'notifications.club_registered_as_co_host' : 'notifications.registered_as_co_host',
            array_merge(
                [
                    'eventScheduleId' => $eventScheduleParticipant->event->id->toString(),
                ],
                $options
            ),
            [
                '%displayName%' => $initiator->getFullNameOrUsername(),
                '%eventName%' => $eventScheduleParticipant->event->name,
                '%time%' => new TimeSpecificZoneTranslationParameter(
                    $eventScheduleParticipant->event->dateTime,
                    'l, F d \a\t h:i A'
                ),
                '%clubTitle%' => $club->title ?? ''
            ]
        );
    }
}
