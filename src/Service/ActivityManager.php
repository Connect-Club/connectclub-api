<?php

namespace App\Service;

use App\DTO\V1\Activity\ActivityItemResponse;
use App\Entity\Activity\Activity;
use App\Entity\Activity\ActivityWithVideoRoomInterface;
use App\Entity\Activity\ClubActivityInterface;
use App\Entity\Activity\ClubRegisteredAsCoHostActivity;
use App\Entity\Activity\ClubScheduledEventMeetingActivity;
use App\Entity\Activity\CustomActivity;
use App\Entity\Activity\EventScheduleActivityInterface;
use App\Entity\Activity\IntroActivity;
use App\Entity\Activity\InvitePrivateVideoRoomActivity;
use App\Entity\Activity\JoinRequestActivityInterface;
use App\Entity\Activity\JoinRequestWasApprovedActivity;
use App\Entity\Activity\RegisteredAsCoHostActivity;
use App\Entity\Activity\RegisteredAsSpeakerActivity;
use App\Entity\Activity\ScheduledEventMeetingActivity;
use App\Entity\Activity\StartedClubVideoRoomActivity;
use App\Entity\Activity\StartedVideoRoomActivity;
use App\Entity\Activity\UserRegisteredActivity;
use App\Entity\User;
use App\Repository\Activity\ActivityRepository;
use App\Repository\Follow\FollowRepository;
use App\Service\Notification\NotificationManager;
use App\Util\TimeZone;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityManager
{
    private FollowRepository $followRepository;
    private ActivityRepository $activityRepository;
    private EntityManagerInterface $entityManager;
    private NotificationManager $notificationManager;
    private TranslatorInterface $translator;
    private LoggerInterface $logger;

    public function __construct(
        FollowRepository $followRepository,
        ActivityRepository $activityRepository,
        EntityManagerInterface $entityManager,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        LoggerInterface $logger
    ) {
        $this->followRepository = $followRepository;
        $this->activityRepository = $activityRepository;
        $this->entityManager = $entityManager;
        $this->notificationManager = $notificationManager;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    public function getActivityTitle(Activity $activity): ?string
    {
        $translatorParameters = $this->getTranslationParameters($activity);

        switch ($activity->getType()) {
            case Activity::TYPE_NEW_JOIN_REQUEST:
                $code = 'new-join-request-title';
                break;
            case Activity::TYPE_WELCOME_ON_BOARDING_FRIEND:
                $code = 'welcome-on-boarding-friend-title';
                break;
            case Activity::TYPE_INVITE_ON_BOARDING:
                $code = 'invite-on-boarding-title';
                break;
            case Activity::TYPE_JOIN_REQUEST_WAS_APPROVED:
                $code = 'join-request-was-approved-title';
                break;
            case Activity::TYPE_CLUB_VIDEO_ROOM_STARTED:
                $code = 'club-video-room-started-title';
                break;
            case Activity::TYPE_VIDEO_ROOM_STARTED:
                $code = 'video-room-started-title';
                break;
            case Activity::TYPE_USER_CLUB_SCHEDULE_EVENT:
            case Activity::TYPE_USER_SCHEDULE_EVENT:
                $code = 'user-schedule-event-title';
                break;
            case Activity::TYPE_USER_CLUB_SCHEDULE_REGISTERED_AS_CO_HOST:
                $code = 'user-club-registered-as-co-host-title';
                break;
            case Activity::TYPE_REGISTERED_AS_CO_HOST:
                $code = 'registered-as-co-host-title';
                break;
            case Activity::TYPE_REGISTERED_AS_SPEAKER:
                /** @var RegisteredAsSpeakerActivity $activity */
                $code = $activity->isForClub ? 'user-club-registered-as-speaker-title' : 'registered-as-speaker-title';
                break;
            case Activity::TYPE_NEW_USER_ASK_INVITE:
                $code = 'new-user-ask-invite-title';
                break;
            case Activity::TYPE_INVITE_PRIVATE_VIDEO_ROOM:
                $code = 'invite-private-video-room-title';
                break;
            default:
                $code = $activity->getType() . '-title';
        }

        $transCode = 'activity.'.$code;
        $trans = $this->translator->trans($transCode, $translatorParameters);

        if ($trans == $transCode || !$trans) {
            return null;
        }

        return $trans;
    }

    public function getActivityDescription(Activity $activity): string
    {
        $type = $activity->getType();
        $translatorParameters = $this->getTranslationParameters($activity);

        switch (get_class($activity)) {
            case StartedClubVideoRoomActivity::class:
                $type = $activity->getVideoRoom()->community->description ?
                        'club-video-room-started-with-name' :
                        'club-video-room-started-without-name';
                break;
            case StartedVideoRoomActivity::class:
                $type = $activity->getVideoRoom()->community->description ?
                    'video-room-started-with-name' :
                    'video-room-started-without-name';
                break;
            case RegisteredAsCoHostActivity::class:
                $type = 'registered-as-co-host';
                break;
            case ClubRegisteredAsCoHostActivity::class:
                $type = 'user-club-registered-as-co-host';
                break;
            case RegisteredAsSpeakerActivity::class:
                $type = $activity->isForClub ? 'user-club-registered-as-speaker' : 'registered-as-speaker';
                break;
            case InvitePrivateVideoRoomActivity::class:
                $type = $activity->getVideoRoom()->community->description ?
                    'invite-private-video-room-with-name' :
                    'invite-private-video-room-without-name';
                break;
            case IntroActivity::class:
                $translatorParameters['%userName%'] = $activity->user->name;
                break;
        }

        return $this->translator->trans('activity.'.$type, $translatorParameters);
    }

    public function getTranslationParameters(Activity $activity): array
    {
        $relatedUserName = implode(
            ', ',
            $activity->nestedUsers->map(fn(User $u) => $u->getFullNameOrId(true))->getValues()
        );
        $translatorParameters = ['%relatedUserName%' => $relatedUserName];

        if ($activity instanceof ClubActivityInterface) {
            $translatorParameters['%clubTitle%'] = $activity->getClub()->title;
        }

        if ($activity instanceof JoinRequestActivityInterface) {
            $translatorParameters['%clubTitle%'] = $activity->getJoinRequest()->club->title;
        }

        if ($activity instanceof JoinRequestWasApprovedActivity) {
            $translatorParameters['%clubRole%'] = $activity->clubRole;
        }

        if ($activity instanceof ActivityWithVideoRoomInterface) {
            $eventSchedule = $activity->getVideoRoom()->eventSchedule;
            $translatorParameters['%meetingName%'] = $activity->getVideoRoom()->community->description ??
                                                     $eventSchedule->name ??
                                                     '';
        }

        if ($activity instanceof EventScheduleActivityInterface) {
            $translatorParameters['%meetingName%'] = $activity->getEventSchedule()->name;
            $translatorParameters['%dateTime%'] = $this->formatDateTime(
                $activity->getEventSchedule()->dateTime,
                $activity->user
            );
            $translatorParameters['%creator%'] = $activity->getEventSchedule()->owner->getFullNameOrId(true);
        }

        return $translatorParameters;
    }

    public function fireActivityForFollowers(Activity $activity, User $user, $pushNotification = null)
    {
        $this->fireActivityForUsers($activity, $this->followRepository->findFollowers($user), $pushNotification);
    }

    public function fireActivityForUsers(Activity $activity, array $users, $pushNotification = null)
    {
        if ($pushNotification) {
            $this->notificationManager->prepareDeviceTokensForParticipants($users);
        }

        $this->notificationManager->setMode(NotificationManager::MODE_BATCH);

        $bulkInsert = $this->activityRepository->bulkInsert();
        foreach ($users as $user) {
            $activity = clone $activity;
            $activity->user = $user;

            $bulkInsert->insertEntity($activity);

            $push = is_callable($pushNotification) ? $pushNotification($user) : $pushNotification;
            if ($push) {
                $this->notificationManager->sendNotifications($user, $push);
            }
        }

        $this->notificationManager->flushBatch();
        $this->notificationManager->setMode(NotificationManager::MODE_SERIAL);

        $this->entityManager->flush();
        $this->activityRepository->executeBulkInsert($bulkInsert);
    }

    private function formatDateTime(int $utcDateTime, User $forUser): ?string
    {
        try {
            return date(
                'l, F d \a\t h:i A',
                TimeZone::getTimestampWithUserTimeZone(
                    $utcDateTime,
                    $forUser
                )
            );
        } catch (Exception $exception) {
            $this->logger->error($exception, [
                'exception' => $exception,
                'timezone' => $forUser->city ? $forUser->city->timeZone : null,
            ]);
        }

        return null;
    }
}
