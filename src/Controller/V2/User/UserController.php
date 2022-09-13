<?php

namespace App\Controller\V2\User;

use App\Annotation\Lock;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V1\PaginatedResponseWithCount;
use App\DTO\V1\User\UserInfoForAdminResponse;
use App\DTO\V2\User\FullUserInfoResponseWithIsBlocked;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use App\Repository\Club\JoinRequestRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\PhoneNumberManager;
use App\Service\SlackClient;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/users")
 */
class UserController extends BaseController
{
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $em)
    {
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    /**
     * @SWG\Post(
     *     description="List users by ids",
     *     summary="List users by ids",
     *     tags={"User", "Following"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(type="array", @SWG\Items(type="integer"))),
     *     @SWG\Response(response="200", description="Success response")
     * )
     * @Route("/subscribe", methods={"POST"}, requirements={"id": "\d+"})
     * @ListResponse(entityClass=FullUserInfoResponseWithIsBlocked::class)
     *
     * @Route("", methods={"POST"})
     */
    public function users(Request $request): JsonResponse
    {
        $response = [];

        if ($this->em->getFilters()->isEnabled('softdeleteable')) {
            $this->em->getFilters()->disable('softdeleteable');
        }

        $userIds = array_unique(array_map('intval', json_decode($request->getContent(), true) ?? []));
        if (!$userIds) {
            return $this->handleResponse([]);
        }

        if (!$this->isGranted('ROLE_UNITY_SERVER') && !$this->isGranted('ROLE_USER_VERIFIED')) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        $currentUser = $this->getUser();
        $usersWithFollowingData = $this->userRepository->findUsersByIdsWithFollowingData(
            $currentUser,
            $userIds,
            true,
            true
        );

        if (count($usersWithFollowingData) !== count($userIds)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $result = [];
        foreach ($usersWithFollowingData as list($user, $isFollower, $isFollowing, $followers, $following, $blocked)) {
            $result[$user->id] = new FullUserInfoResponseWithIsBlocked(
                $user,
                $isFollowing,
                $isFollower,
                $followers,
                $following,
                $blocked
            );
        }

        foreach ($userIds as $userId) {
            $response[] = $result[$userId];
        }

        if (!$this->em->getFilters()->isEnabled('softdeleteable')) {
            $this->em->getFilters()->enable('softdeleteable');
        }

        return $this->handleResponse($response);
    }

    /**
     * @SWG\Post(
     *     description="Ping user from video room",
     *     summary="Ping user from video room",
     *     tags={"User"},
     *     @SWG\Response(response="200", description="Success ping")
     * )
     * @ViewResponse(
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "Friend not found"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, "Video room not found"},
     *     }
     * )
     * @Route("/{friendId}/{videoRoomId}/ping", requirements={"userId": "\d+"}, methods={"POST"})
     */
    public function ping(
        int $friendId,
        string $videoRoomId,
        FollowRepository $followRepository,
        VideoRoomRepository $videoRoomRepository,
        NotificationManager $notificationManager,
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        TranslatorInterface $translator
    ): JsonResponse {
        $user = $this->getUser();

        $friend = $followRepository->findFriendById($user, $friendId);
        if (!$friend) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $videoRoom = $videoRoomRepository->findOneByName($videoRoomId);
        if (!$videoRoom) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $videoRoom->addInvitedUser($friend);
        $videoRoomRepository->save($videoRoom);

        $speakersData = $videoMeetingParticipantRepository->findSpeakersForVideoRoom($videoRoom);

        $isSpeaker = false;
        foreach ($speakersData as $k => $speaker) {
            if ($speaker['user_id'] == $user->id) {
                $isSpeaker = true;
                unset($speakersData[$k]);
                $speakersData = array_values($speakersData);
                break;
            }
        }

        $getSpeakerName = function (array $speaker) {
            return $speaker['name'] . ' ' . mb_substr($speaker['surname'], 0, 1) . '.';
        };

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

        if ($isSpeaker) {
            $message = $videoRoom->community->description ?
                'notifications.ping_user_from_scene_room_with_name' :
                'notifications.ping_user_from_scene_room_without_name';
        } else {
            $message = $videoRoom->community->description ?
                'notifications.ping_user_from_listeners_room_with_name' :
                'notifications.ping_user_from_listeners_room_without_name';
        }

        $translatorParameters = [
            '%speakers%' => $speakersText ?? null,
            '%meetingName%' => $videoRoom->community->description,
            '%initiator%' => $user->name . ' ' . mb_substr($user->surname ?? '', 0, 1) . '.',
        ];

        $message = $translator->trans($message, $translatorParameters);

        $notificationManager->sendNotifications(
            $friend,
            new ReactNativeVideoRoomNotification(
                $videoRoom,
                'notifications.ping_user_title',
                $message,
                [
                    PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                    PushNotification::PARAMETER_SPECIFIC_KEY => 'ping-video-room-new',
                    PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(300, 300)
                ]
            )
        );

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get(
     *     produces={"application/json"},
     *     description="Get users",
     *     summary="Get users",
     *     @SWG\Parameter(
     *         in="query",
     *         name="filter",
     *         type="string",
     *         schema=@SWG\Schema(type="object")
     *     ),
     *     @SWG\Response(response="200", description="All users"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     tags={"User"}
     * )
     * @ListResponse(
     *     entityClass=UserInfoForAdminResponse::class,
     *     pagination=true,
     *     paginationByLastValue=true,
     *     paginationWithTotalCount=true
     * )
     * @Route("", methods={"GET"})
     */
    public function all(
        UserRepository $userRepository,
        Request $request,
        EntityManagerInterface $entityManager
    ) : JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_UNITY_SERVER')) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if ($entityManager->getFilters()->isEnabled('softdeleteable')) {
            $entityManager->getFilters()->disable('softdeleteable');
        }

        $query = $userRepository->createQueryBuilder('e')
            ->addSelect('i')
            ->addSelect('i2')
            ->addSelect('c1')
            ->addSelect('c2')
            ->addSelect('a')
            ->addSelect('devices')
            ->leftJoin('e.accessTokens', 'a')
            ->leftJoin('e.invite', 'i')
            ->leftJoin('e.city', 'c1')
            ->leftJoin('c1.country', 'c2')
            ->leftJoin('e.interests', 'i2')
            ->leftJoin('e.devices', 'devices')
            ->where('e.state != :state')
            ->setParameter('state', User::STATE_OLD_USER)
        ;

        $filters = $request->query->get('filter');
        if ($filters) {
            $filters = json_decode($filters, true) ?? [];
        } else {
            $filters = [];
        }

        if (isset($filters['badges'])) {
            $query
                ->andWhere('JSONB_EXISTS(e.badges, :badges) = TRUE')
                ->setParameter('badges', $filters['badges'], 'jsonb');
            unset($filters['badges']);
        }

        if (isset($filters['role'])) {
            $query
                ->leftJoin('e.roles', 'roles')
                ->andWhere('roles.role = :role')
                ->setParameter('role', $filters['role'], ParameterType::STRING);
        }

        $this->handleArrayFilters(User::class, $filters, $query);

        $paginateQuery = clone $query;
        $totalCount = $paginateQuery->resetDQLPart('select')
                                    ->resetDQLPart('orderBy')
                                    ->select('COUNT(DISTINCT e)')
                                    ->getQuery()
                                    ->getSingleScalarResult();

        list($users, $lastValue) = $this->paginateByLastCursor($query, $request, 'id', 'DESC');

        $usersInfoResponses = array_map(fn(User $user) => new UserInfoForAdminResponse(
            $user,
            false,
            false,
            0,
            0
        ), $users);

        if (!$entityManager->getFilters()->isEnabled('softdeleteable')) {
            $entityManager->getFilters()->enable('softdeleteable');
        }

        return $this->handleResponse(new PaginatedResponseWithCount($usersInfoResponses, $lastValue, $totalCount));
    }

    /**
     * @SWG\Get(
     *     produces={"application/json"},
     *     description="Get join requests",
     *     summary="Get join requests",
     *     @SWG\Response(response="200", description="Join requests"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     tags={"User"}
     * )
     * @ListResponse(
     *     entityClass=ClubSlimResponse::class,
     * )
     * @Route("/{userId}/join-requests", methods={"GET"})
     */
    public function joinRequests(
        string $userId,
        JoinRequestRepository $joinRequestRepository
    ) : JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $joinRequests = $joinRequestRepository->findBy([
            'author' => [
                'id' => (int) $userId
            ],
            'status' => JoinRequest::STATUS_MODERATION,
        ]);

        $response = array_map(
            fn(JoinRequest $joinRequest) => new ClubSlimResponse($joinRequest->club),
            $joinRequests
        );

        return $this->handleResponse($response);
    }

    /**
     * @SWG\Post(
     *     produces={"application/json"},
     *     description="Create deletion request",
     *     summary="Create deletion request",
     *     @SWG\Response(response="200", description="Join requests"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     tags={"User"}
     * )
     * @ViewResponse()
     * @Lock(code="create_deletion_request", personal=true)
     * @Route("/delete-request", methods={"POST"})
     */
    public function deleteRequest(
        SlackClient $slackClient,
        PhoneNumberManager $phoneNumberManager
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $parameters = [
            $currentUser->name,
            $currentUser->surname,
            $currentUser->id,
            $currentUser->phone ? $phoneNumberManager->formatE164($currentUser->phone) : '(Empty)',
            $currentUser->username
        ];
        $message = "🚮 New deletion request (%s %s)\r\n🚹User ID: %d\r\n📱Phone number: %s\r\n💔 Username: %s";

        if ($currentUser->instagram) {
            $parameters[] = $currentUser->instagram;
            $message .= "\r\n🤳 Instagram: %s";
        }

        if ($currentUser->linkedin) {
            $parameters[] = $currentUser->linkedin;
            $message .= "\r\n💼 Linkedin: %s";
        }

        if ($currentUser->intercomId) {
            $parameters[] = $currentUser->intercomId;
            $message .= "\r\n💭 Intercom ID: %s";
        }

        $parameters[] = date('d.m.Y H:i:s');
        $message .= "\r\n🕥 Date: %s UTC";

        if ($currentUser->username) {
            $parameters[] = 'https://connect.club/user/'.$currentUser->username;
            $message .= "\r\n👤 User link: %s";
        }

        $message = sprintf($message, ...$parameters);

        $slackClient->sendMessage('user-deletion-requests', $message);

        return $this->handleResponse([]);
    }
}
