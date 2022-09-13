<?php

namespace App\Service;

use App\Entity\Activity\StartedClubVideoRoomActivity;
use App\Entity\Activity\StartedVideoRoomActivity;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Repository\Event\EventScheduleParticipantRepository;
use App\Repository\Event\EventScheduleSubscriptionRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\Push\PushNotification;
use Symfony\Component\Lock\LockFactory;

class VideoRoomNotifier
{
    private ActivityManager $activityManager;
    private VideoRoomRepository $videoRoomRepository;
    private FollowRepository $followRepository;
    private EventScheduleManager $eventScheduleManager;
    private EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository;
    private EventScheduleParticipantRepository $eventScheduleParticipantRepository;
    private LockFactory $lockFactory;

    public function __construct(
        ActivityManager $activityManager,
        VideoRoomRepository $videoRoomRepository,
        FollowRepository $followRepository,
        EventScheduleManager $eventScheduleManager,
        EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository,
        EventScheduleParticipantRepository $eventScheduleParticipantRepository,
        LockFactory $lockFactory
    ) {
        $this->activityManager = $activityManager;
        $this->videoRoomRepository = $videoRoomRepository;
        $this->followRepository = $followRepository;
        $this->eventScheduleManager = $eventScheduleManager;
        $this->eventScheduleSubscriptionRepository = $eventScheduleSubscriptionRepository;
        $this->eventScheduleParticipantRepository = $eventScheduleParticipantRepository;
        $this->lockFactory = $lockFactory;
    }

    public function notifyStarted(VideoRoom $videoRoom): void
    {
        $lock = $this->lockFactory->createLock('video_room_notify_'.$videoRoom->community->name, 1000, true)->acquire();
        if (!$lock) {
            return;
        }

        if ($videoRoom->notificationStartRoomHandled) {
            return;
        }

        $owner = $videoRoom->community->owner;

        $subscribedUsers = [];
        if ($videoRoom->eventSchedule) {
            $subscriptions = $this->eventScheduleSubscriptionRepository->findSubscriptionOnEvent(
                $videoRoom->eventSchedule
            );

            $subscribedUsers = array_combine(
                array_map(fn(EventScheduleSubscription $ess) => $ess->user->id, $subscriptions),
                array_map(fn(EventScheduleSubscription $ess) => $ess->user, $subscriptions)
            );

            $club = $videoRoom->eventSchedule->club;
        }

        if ($videoRoom->eventSchedule) {
            if ($videoRoom->isPrivate) {
                $participants = array_map(
                    fn(EventScheduleParticipant $p) => $p->user,
                    $this->eventScheduleParticipantRepository->findBy(['event' => $videoRoom->eventSchedule])
                );
            } else {
                $participants = $this->eventScheduleManager->fetchParticipantsForEventSchedule(
                    $videoRoom->eventSchedule
                );
            }

            $participants = array_filter(
                $participants,
                fn(User $u): bool => (bool) array_intersect($u->languages, $videoRoom->eventSchedule->languages)
            );
        } else {
            $participants = $this->followRepository->findFollowers($owner, null, $videoRoom->language);
        }

        $participants = array_filter(
            $participants,
            fn(User $follower) => !isset($subscribedUsers[$follower->id]) && !$follower->equals($owner)
        );

        if (isset($club)) {
            $activity = new StartedClubVideoRoomActivity($club, $videoRoom, $owner, $owner);
        } else {
            $activity = new StartedVideoRoomActivity($videoRoom, $owner, $owner);
        }

        $this->activityManager->fireActivityForUsers(
            $activity,
            $participants,
            new ReactNativeVideoRoomNotification(
                $videoRoom,
                $this->activityManager->getActivityTitle($activity),
                $this->activityManager->getActivityDescription($activity),
                [
                    PushNotification::PARAMETER_SPECIFIC_KEY => $activity->getType(),
                    PushNotification::PARAMETER_INITIATOR_ID => $videoRoom->community->owner->id,
                    PushNotification::PARAMETER_IMAGE => $videoRoom->community->owner->getAvatarSrc(300, 300),
                    PushNotification::PARAMETER_SECOND_IMAGE => isset($club) && isset($club->avatar) ?
                                                                $club->avatar->getResizerUrl(300, 300) : null,
                ],
                'video-room',
                [
                    '%clubTitle%' => $club->title ?? ''
                ]
            )
        );

        $videoRoom->notificationStartRoomHandled = true;
        $this->videoRoomRepository->save($videoRoom);
    }
}
