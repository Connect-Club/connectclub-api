<?php

namespace App\EventSubscriber\Twilio;

use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomParticipantConnectedEvent;
use App\Repository\Follow\FollowRepository;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendNotificationNewParticipantSubscriber implements EventSubscriberInterface
{
    private VideoMeetingParticipantRepository $videoMeetingParticipantRepository;
    private NotificationManager $notificationManager;
    private TranslatorInterface $translator;
    private FollowRepository $followRepository;

    public function __construct(
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        FollowRepository $followRepository
    ) {
        $this->videoMeetingParticipantRepository = $videoMeetingParticipantRepository;
        $this->notificationManager = $notificationManager;
        $this->translator = $translator;
        $this->followRepository = $followRepository;
    }

    public function onVideoRoomParticipantConnectedEvent(VideoRoomParticipantConnectedEvent $event)
    {
        $videoRoomActiveMeeting = $event->videoMeeting;
        if ($_ENV['STAGE'] != 1) {
            return;
        }

        //Only first connection as speaker in video room with event schedule with club
        if (!$videoRoomActiveMeeting ||
            !$videoRoomActiveMeeting->videoRoom->eventSchedule ||
            !$videoRoomActiveMeeting->videoRoom->eventSchedule->club ||
            !$event->user ||
            !$event->endpointAllowIncomingMedia) {
            return;
        }

        //If user reconnected right now - skip this event
        $userAlreadyExistsAsSpeaker = $this->videoMeetingParticipantRepository->findOneBy([
            'videoMeeting' => [
                'videoRoom' => $videoRoomActiveMeeting->videoRoom
            ],
            'participant' => $event->user,
            'endpointAllowIncomingMedia' => true,
        ]);

        if ($userAlreadyExistsAsSpeaker) {
            return;
        }

        $followersInClub = $this->followRepository->findFollowers(
            $event->user,
            $videoRoomActiveMeeting->videoRoom->eventSchedule->club
        );

        $speakersData = $this->videoMeetingParticipantRepository->findSpeakersForVideoRoom($event->videoRoom);

        //Sorting
        $currentPosition = null;
        foreach ($speakersData as $k => $speaker) {
            if ($speaker['id'] === $event->user->id) {
                $currentPosition = $k;
                break;
            }
        }
        //not 0 and not null and exists speakers in video room
        if ($currentPosition && isset($speakersData[0])) {
            $firstSpeaker = $speakersData[0];
            $currentSpeaker = $speakersData[$currentPosition];

            //Move current speaker to top and first speaker to current speaker old position
            $speakersData[0] = $currentSpeaker;
            $speakersData[$currentPosition] = $firstSpeaker;
        }

        $getSpeakerName = function (array $speaker) {
            return $speaker['name'] . ' ' . mb_substr($speaker['surname'], 0, 1) . '.';
        };

        $translator = $this->translator;
        $speakersText = '';
        $countSpeakers = count($speakersData);
        switch (true) {
            case $countSpeakers == 1:
                $speakersText = $getSpeakerName($speakersData[0]);
                $speakersText .= ' '.$translator->trans('notifications.ping_user_from_listeners_speaker_info');
                break;
            case $countSpeakers == 2:
                $speakersText = $getSpeakerName($speakersData[0]) . ' and ' . $getSpeakerName($speakersData[1]);
                $speakersText .= ' '.$translator->trans('notifications.ping_user_from_listeners_speakers_info');
                break;
            case $countSpeakers == 3:
                //phpcs:ignore
                $speakersText = $getSpeakerName($speakersData[0]) . ',' . $getSpeakerName($speakersData[1]) . ' and ' . $getSpeakerName($speakersData[2]);
                $speakersText .= ' '.$translator->trans('notifications.ping_user_from_listeners_speakers_info');
                break;
            case $countSpeakers > 3:
                //phpcs:ignore
                $speakersText = $getSpeakerName($speakersData[0]) . ',' . $getSpeakerName($speakersData[1]) . ', ' . $getSpeakerName($speakersData[2]) . ' and others';
                $speakersText .= ' '.$translator->trans('notifications.ping_user_from_listeners_speakers_info');
                break;
        }

        $club = $event->videoRoom->eventSchedule->club;
        $avatar = $club->avatar;

        $this->notificationManager->setMode(NotificationManager::MODE_BATCH);
        foreach ($followersInClub as $follower) {
            $this->notificationManager->sendNotifications(
                $follower,
                new ReactNativeVideoRoomNotification(
                    $event->videoRoom,
                    'notifications.followed_user_mutual_club_title',
                    'notifications.followed_user_mutual_club',
                    [
                        PushNotification::PARAMETER_SPECIFIC_KEY => 'user-speak-on-club-scene-for-followers',
                        PushNotification::PARAMETER_INITIATOR_ID => $event->user->id,
                        PushNotification::PARAMETER_IMAGE => $event->user->getAvatarSrc(300, 300),
                        PushNotification::PARAMETER_SECOND_IMAGE => $avatar ? $avatar->getResizerUrl(300, 300): null,
                    ],
                    'video-room',
                    [
                        '%speakers%' => $speakersText,
                        '%meetingName%' => $event->videoRoom->eventSchedule->name,
                        '%clubTitle%' => $event->videoRoom->eventSchedule->club->title,
                    ]
                )
            );
        }
        $this->notificationManager->flushBatch();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VideoRoomParticipantConnectedEvent::class => ['onVideoRoomParticipantConnectedEvent', 255],
        ];
    }
}
