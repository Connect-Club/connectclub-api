<?php

namespace App\Service;

use App\Entity\Activity\ClubScheduledEventMeetingActivity;
use App\Entity\Activity\ScheduledEventMeetingActivity;
use App\Entity\Club\ClubParticipant;
use App\Entity\Event\EventSchedule;
use App\Entity\User;
use App\Repository\Club\ClubParticipantRepository;
use App\Repository\Follow\FollowRepository;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;

class EventScheduleManager
{
    private ClubParticipantRepository $clubParticipantRepository;
    private FollowRepository $followRepository;
    private ActivityManager $activityManager;

    public function __construct(
        ActivityManager $activityManager,
        ClubParticipantRepository $clubParticipantRepository,
        FollowRepository $followRepository
    ) {
        $this->activityManager = $activityManager;
        $this->clubParticipantRepository = $clubParticipantRepository;
        $this->followRepository = $followRepository;
    }

    public function createActivityForEventSchedule(
        EventSchedule $eventSchedule,
        User $initiator,
        array $notificationParticipants
    ) {
        if ($eventSchedule->club) {
            $activity = new ClubScheduledEventMeetingActivity(
                $eventSchedule->club,
                $eventSchedule,
                $initiator,
                $initiator
            );
        } else {
            $activity = new ScheduledEventMeetingActivity($eventSchedule, $initiator, $initiator);
        }

        $notificationParticipants = array_filter(
            $notificationParticipants,
            fn(User $u): bool => (bool) array_intersect($u->languages, $eventSchedule->languages)
        );

        $activityManager = $this->activityManager;
        $this->activityManager->fireActivityForUsers(
            $activity,
            $notificationParticipants,
            function (User $participant) use ($initiator, $eventSchedule, $activityManager) {
                if ($eventSchedule->club) {
                    $activity = new ClubScheduledEventMeetingActivity(
                        $eventSchedule->club,
                        $eventSchedule,
                        $participant,
                        $initiator
                    );
                } else {
                    $activity = new ScheduledEventMeetingActivity($eventSchedule, $participant, $initiator);
                }

                return new ReactNativePushNotification(
                    'event-schedule',
                    $activityManager->getActivityTitle($activity),
                    $activityManager->getActivityDescription($activity),
                    [
                        'eventScheduleId' => $eventSchedule->id->toString(),
                        PushNotification::PARAMETER_INITIATOR_ID => $initiator->id,
                        PushNotification::PARAMETER_SPECIFIC_KEY => 'event-scheduled',
                        PushNotification::PARAMETER_IMAGE => $initiator->getAvatarSrc(300, 300),
                        //phpcs:ignore
                        PushNotification::PARAMETER_SECOND_IMAGE => $eventSchedule->club && $eventSchedule->club->avatar ?
                            $eventSchedule->club->avatar->getResizerUrl(300, 300) : null,
                    ],
                    [
                        '%clubTitle%' => $eventSchedule->club->title ?? ''
                    ]
                );
            }
        );
    }


    public function fetchParticipantsForEventIgnoreClubParticipants(EventSchedule $eventSchedule): array
    {
        if (!$eventSchedule->club) {
            return [];
        }

        $clubParticipants = array_map(
            fn(ClubParticipant $cp) => $cp->user,
            $this->clubParticipantRepository->findClubParticipants($eventSchedule->club)
        );

        $followers = $this->followRepository->findFollowers($eventSchedule->owner);

        foreach ($followers as $i => $follower) {
            foreach ($clubParticipants as $clubParticipant) {
                if ($follower->equals($clubParticipant)) {
                    unset($followers[$i]);
                }
            }
        }

        return array_values($followers);
    }

    /** @return User[] */
    public function fetchParticipantsForEventSchedule(EventSchedule $eventSchedule): array
    {
        if ($eventSchedule->club && $eventSchedule->forMembersOnly) {
            $notificationParticipants = array_map(
                fn(ClubParticipant $cp) => $cp->user,
                $this->clubParticipantRepository->findClubParticipants($eventSchedule->club)
            );
        } elseif ($eventSchedule->club && !$eventSchedule->forMembersOnly) {
            $clubParticipants = array_map(
                fn(ClubParticipant $cp) => $cp->user,
                $this->clubParticipantRepository->findClubParticipants($eventSchedule->club)
            );
            $followers = $this->followRepository->findFollowers($eventSchedule->owner, null, $eventSchedule->language);

            $notificationParticipants = [];

            foreach ($clubParticipants as $user) {
                $notificationParticipants[$user->id] = $user;
            }
            foreach ($followers as $follower) {
                $notificationParticipants[$follower->id] = $follower;
            }

            $notificationParticipants = array_values($notificationParticipants);
        } else {
            $notificationParticipants = $this->followRepository->findFollowers($eventSchedule->owner);
        }

        return $notificationParticipants;
    }
}
