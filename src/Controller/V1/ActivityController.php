<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Activity\ActivityCustomResponse;
use App\DTO\V1\Activity\ActivityEventScheduledItemResponse;
use App\DTO\V1\Activity\ActivityJoinDiscordResponse;
use App\DTO\V1\Activity\ActivityUserRegisteredItemResponse;
use App\DTO\V1\Activity\ActivityWaitingListUserItemResponse;
use App\DTO\V1\Activity\ActivityWithVideoRoomItemResponse;
use App\DTO\V1\Activity\JoinRequestActivityResponse;
use App\DTO\V1\Activity\JoinRequestWasApprovedActivityResponse;
use App\DTO\V1\Activity\NewClubInviteActivityResponse;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\Activity\ActivityItemResponse;
use App\Entity\Activity\Activity;
use App\Entity\Activity\ActivityWithVideoRoomInterface;
use App\Entity\Activity\CustomActivity;
use App\Entity\Activity\EventScheduleActivityInterface;
use App\Entity\Activity\JoinDiscordActivity;
use App\Entity\Activity\JoinRequestActivityInterface;
use App\Entity\Activity\JoinRequestWasApprovedActivity;
use App\Entity\Activity\NewClubInviteActivity;
use App\Entity\Activity\NewUserFromWaitingListActivity;
use App\Entity\Activity\NewUserRegisteredByInviteCodeActivity;
use App\Entity\Activity\UserRegisteredActivity;
use App\Entity\User;
use App\Repository\Activity\ActivityRepository;
use App\Repository\UserRepository;
use App\Service\ActivityManager;
use App\Service\Notification\NotificationManager;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/activity")
 */
class ActivityController extends BaseController
{
    private ActivityRepository $activityRepository;

    public function __construct(ActivityRepository $activityRepository)
    {
        $this->activityRepository = $activityRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get users activity",
     *     summary="Get users activity",
     *     tags={"Activity"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ActivityItemResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @Route("", methods={"GET"})
     */
    public function activity(
        UserRepository $userRepository,
        ActivityManager $activityManager
    ): JsonResponse {
        $user = $this->getUser();

        /** @var Activity[] $activities */
        [$activities] = $this->activityRepository->findActivity($user, 0, 40);

        /** @var UserRegisteredActivity[] $registeredUsersEvents */
        $registeredUsersEvents = array_filter(
            $activities,
            fn(Activity $activity) => $activity instanceof UserRegisteredActivity
        );

        /** @var int[] $registeredUsersIdsFromEvents */
        $registeredUsersIdsFromEvents = array_map(
            fn(UserRegisteredActivity $activity) => $activity->nestedUsers->first()->id,
            $registeredUsersEvents
        );

        $followingData = $userRepository->findUsersByIdsWithFollowingData(
            $user,
            $registeredUsersIdsFromEvents,
            false,
            true
        );
        $preparedFollowingData = [];
        foreach ($followingData as [$user, $isFollower, $isFollowing]) {
            $preparedFollowingData[$user->id] = ['isFollower' => $isFollower, 'isFollowing' => $isFollowing];
        }

        $response = [];
        foreach ($activities as $activity) {
            $type = $activity->getType();

            $responseHead = $activityManager->getActivityTitle($activity);
            $responseTitle = $activityManager->getActivityDescription($activity);

            switch (get_class($activity)) {
                case NewUserFromWaitingListActivity::class:
                    /** @var NewUserFromWaitingListActivity|NewUserRegisteredByInviteCodeActivity $activity */
                    $activityItemResponse = new ActivityWaitingListUserItemResponse($activity, $type);
                    break;
                case UserRegisteredActivity::class:
                    /** @var UserRegisteredActivity $activity */
                    /** @var User $registeredUser */
                    $registeredUser = $activity->nestedUsers->first();

                    $activityItemResponse = new ActivityUserRegisteredItemResponse(
                        $preparedFollowingData[$registeredUser->id]['isFollowing'] ?? false,
                        $preparedFollowingData[$registeredUser->id]['isFollower'] ?? false,
                        $activity,
                        $type
                    );
                    break;
                case JoinRequestWasApprovedActivity::class:
                    /** @var JoinRequestWasApprovedActivity $activity */
                    $activityItemResponse = new JoinRequestWasApprovedActivityResponse($activity, $type);
                    break;
                case NewClubInviteActivity::class:
                    $activityItemResponse = new NewClubInviteActivityResponse($activity, $type);
                    break;
                case JoinDiscordActivity::class:
                    $activityItemResponse = new ActivityJoinDiscordResponse($activity, $type);
                    break;
                default:
                    if ($activity instanceof ActivityWithVideoRoomInterface) {
                        $activityItemResponse = new ActivityWithVideoRoomItemResponse($activity, $type);
                    } elseif ($activity instanceof EventScheduleActivityInterface) {
                        $activityItemResponse = new ActivityEventScheduledItemResponse($activity, $type);
                    } elseif ($activity instanceof JoinRequestActivityInterface) {
                        $activityItemResponse = new JoinRequestActivityResponse($activity, $type);
                    } elseif ($activity instanceof CustomActivity) {
                        $responseTitle = $activity->title;
                        $activityItemResponse = new ActivityCustomResponse($activity);
                    } else {
                        $activityItemResponse = new ActivityItemResponse($activity, $type);
                    }
            }

            $activityItemResponse->head = $responseHead;
            $activityItemResponse->title = $responseTitle;

            $response[] = $activityItemResponse;
        }

        return $this->handleResponse(new PaginatedResponse($response, null));
    }

    /**
     * @SWG\Post(
     *     description="Read activity feed",
     *     summary="Read activity feed",
     *     tags={"Activity"},
     *     @SWG\Response(response="200", description="Sucess read")
     * )
     * @ViewResponse()
     * @Route("/{id}/read", methods={"POST"})
     */
    public function read(string $id, NotificationManager $notificationManager): JsonResponse
    {
        $user = $this->getUser();

        $activity = $this->activityRepository->findOneBy(['id' => $id, 'user' => $this->getUser()]);
        if (!$activity) {
            return $this->handleResponse([ErrorCode::V1_ERROR_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $this->activityRepository->updateActivityFeedAtMessage($user, $activity);

//        $countUnreadActivities = $this->activityRepository->findCountNewActivities($user);
//        $notificationManager->sendNotifications(
//            $user,
//            new ReactNativePushNotification(
//                'notifications-counter-update',
//                null,
//                null,
//                ['badge' => $countUnreadActivities]
//            )
//        );

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Delete(
     *     description="Delete activity feed",
     *     summary="Delete activity feed",
     *     tags={"Activity"},
     *     @SWG\Response(response="200", description="Sucess delete")
     * )
     * @ViewResponse()
     * @Route("/{id}", methods={"DELETE"})
     */
    public function delete(string $id): JsonResponse
    {
        $user = $this->getUser();

        $activity = $this->activityRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (!$activity) {
            return $this->handleResponse([]);
        }

        $this->activityRepository->remove($activity);

        return $this->handleResponse([]);
    }
}
