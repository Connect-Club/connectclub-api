<?php

namespace App\Controller\V1;

use App\ConnectClub;
use App\Controller\BaseController;
use App\DTO\V1\InterfaceResponse;
use App\Repository\Follow\FollowRepository;
use App\Repository\Activity\ActivityRepository;
use App\Repository\SettingsRepository;
use App\Repository\User\PhoneContactRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Swagger\ViewResponse;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/interface")
 */
class InterfaceController extends BaseController
{
    private ActivityRepository $activityRepository;
    private FollowRepository $followRepository;
    private UserRepository $userRepository;

    public function __construct(
        ActivityRepository $activityRepository,
        FollowRepository $followRepository,
        UserRepository $userRepository
    ) {
        $this->activityRepository = $activityRepository;
        $this->followRepository = $followRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @SWG\Get(
     *     description="Read information with counters",
     *     summary="Read information with counters",
     *     tags={"Interface"},
     *     @SWG\Response(response="200", description="Success response")
     * )
     * @ViewResponse(entityClass=InterfaceResponse::class)
     * @Route("", methods={"GET"})
     */
    public function information(
        PhoneContactRepository $phoneContactRepository,
        VideoRoomRepository $videoRoomRepository,
        SettingsRepository $settingsRepository
    ): JsonResponse {
        $user = $this->getUser();

        $countActivities = $this->activityRepository->findCountNewActivities($user);
        $countOnlineFriends = $this->followRepository->findOnlineFriendsCount($user);
        $countPendingContacts = $phoneContactRepository->findCountPendingPhoneContacts($user);

        $response = new InterfaceResponse(
            !$user->readNotificationNewInvites,
            $countActivities,
            $user->freeInvites < 0 ? 0 : $user->freeInvites,
            $countPendingContacts,
            $countOnlineFriends
        );

        $response->communityLink = ConnectClub::getTelegramChannelForLanguage($user);
        $response->joinDiscordLink = $_ENV['JOIN_DISCORD_LINK'];

        if ($checkInRoom = $videoRoomRepository->findOneBy(['isReception' => true])) {
            $response->checkInRoomId = $checkInRoom->community->name;
            $response->checkInRoomPass = $checkInRoom->community->password;
        }

        $settings = $settingsRepository->findActualSettings();
        $response->showFestivalBanner = $settings->showFestivalBanner;

        return $this->handleResponse($response);
    }

    /**
     * @SWG\Post(
     *     description="Read notification new invites",
     *     summary="Read notification new invites",
     *     tags={"Interface"},
     *     @SWG\Response(response="200", description="Success read new free invites notifications")
     * )
     * @Route("/read-notification-new-invites", methods={"POST"})
     * @ViewResponse()
     */
    public function readNotificationNewInvites(): JsonResponse
    {
        $user = $this->getUser();
        $user->readNotificationNewInvites = true;

        $this->userRepository->save($user);

        return $this->handleResponse([]);
    }
}
