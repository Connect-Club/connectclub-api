<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\PaginatedResponseWithCount;
use App\DTO\V2\User\CountersResponse;
use App\DTO\V2\User\FollowedByShortInfoResponse;
use App\DTO\V2\User\FriendInfoResponse;
use App\DTO\V2\User\MutualFriendsPaginatedResponse;
use App\DTO\V2\User\UserInfoResponse;
use App\DTO\V2\User\UserInfoWithFollowingData;
use App\Entity\Activity\ConnectYouBackActivity;
use App\Entity\Activity\NewFollowerActivity;
use App\Entity\Follow\Follow;
use App\Entity\User;
use App\Repository\Activity\ConnectYouBackActivityRepository;
use App\Repository\Activity\NewFollowerActivityRepository;
use App\Repository\Club\ClubInviteRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\User\UserElasticRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\MatchingClient;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Annotation\Route;

/** @Route("/follow") */
class FollowController extends BaseController
{
    private UserRepository $userRepository;
    private FollowRepository $followRepository;

    public function __construct(UserRepository $userRepository, FollowRepository $followRepository)
    {
        $this->userRepository = $userRepository;
        $this->followRepository = $followRepository;
    }

    /**
     * @SWG\Post(
     *     description="Subscribe to users",
     *     summary="Subscribe to users",
     *     tags={"User", "Following"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(type="array", @SWG\Items(type="integer"))))
     *     @SWG\Response(response="200", description="Success response")
     * )
     * @Route("/subscribe", methods={"POST"}, requirements={"id": "\d+"})
     * @ViewResponse(
     *     errorCodesMap={
     *         {Response::HTTP_BAD_REQUEST, ErrorCode::V1_BAD_REQUEST, "Incorrect request"}
     *     }
     * )
     */
    public function subscribe(
        Request $request,
        EntityManagerInterface $em,
        NewFollowerActivityRepository $newFollowerActivityRepository,
        ConnectYouBackActivityRepository $connectYouBackActivityRepository,
        MatchingClient $matchingClient,
        LockFactory $lockFactory
    ): JsonResponse {
        $currentUser = $this->getUser();

        $userIds = array_unique(array_map('intval', json_decode($request->getContent(), true) ?? []));
        $key = array_search($currentUser->id, $userIds);
        if ($key !== false) {
            unset($userIds[$key]);
        }

        if (!$userIds) {
            return $this->createErrorResponse([ErrorCode::V1_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        }

        $users = $this->followRepository->findUsersNotAlreadyFollowedByIds($currentUser, $userIds);
        foreach ($users as $user) {
            $lockFactory->createLock('follow_'.$currentUser->id.'_'.$user->id)->acquire(true);

            if ($this->followRepository->findOneBy(['follower' => $user, 'user' => $currentUser])) {
                if (!$connectYouBackActivityRepository->findActivity($currentUser, $user)) {
                    $em->persist(new ConnectYouBackActivity($user, $currentUser));
                }
            } else {
                if (!$newFollowerActivityRepository->findActivity($currentUser, $user)) {
                    $em->persist(new NewFollowerActivity($user, $currentUser));
                }
            }

            $em->persist(new Follow($currentUser, $user));

            $matchingClient->publishEventOwnedBy('userFollowAdd', $currentUser, ['userId' => $user->id]);
        }
        $em->flush();

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Unsubscribe from user",
     *     summary="Unsubscribe from user",
     *     tags={"User", "Following"},
     *     @SWG\Response(response="200", description="Success response")
     * )
     * @Route("/{id}/unsubscribe", methods={"POST"}, requirements={"id": "\d+"})
     * @ViewResponse(errorCodesMap={
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_ERROR_USER_NOT_FOLLOWED, "Current user not subscribed"},
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "Target user not found in database"},
     * })
     */
    public function unsubscribe(int $id, MatchingClient $matchingClient): JsonResponse
    {
        $currentUser = $this->getUser();

        if (!$targetUser = $this->userRepository->find($id)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        /** @phpstan-ignore-next-line */
        $currentUserFollow = $targetUser->followers->matching(Criteria::create()->where(
            Criteria::expr()->eq('follower', $currentUser)
        ))->first();

        if (!$currentUserFollow) {
            return $this->createErrorResponse([ErrorCode::V1_ERROR_USER_NOT_FOLLOWED], Response::HTTP_NOT_FOUND);
        }

        $this->followRepository->remove($currentUserFollow);

        $matchingClient->publishEventOwnedBy('userFollowRemove', $currentUser, ['userId' => $targetUser->id]);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get(
     *     description="Get user following",
     *     summary="Get user following",
     *     tags={"User", "Following"},
     *     @SWG\Parameter(
     *         in="query",
     *         name="exceptMutual",
     *         type="boolean",
     *         description="Whether or not to exclude friends"
     *     ),
     *     @SWG\Parameter(in="query", name="search", type="string", required=false, description="Search query"),
     *     @SWG\Response(response="200", description="Get users response")
     * )
     * @ListResponse(
     *     pagination=true,
     *     paginationByLastValue=true,
     *     entityClass=UserInfoWithFollowingData::class,
     *     enableOrderBy=false,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found"}
     *     }
     * )
     * @Route("/{id}/following", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function following(
        UserElasticRepository $userElasticRepository,
        Request $request,
        int $id
    ): JsonResponse {
        $currentUser = $this->getUser();

        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);
        $searchQuery = mb_strtolower($request->query->get('search'));

        $userIds = null;
        if ($searchQuery) {
            [$userIds] = $userElasticRepository->findIdsByQuery($searchQuery);
        }

        if ($request->query->getBoolean('exceptMutual')) {
            list($following, $lastValue) = $this->followRepository->findNotMutualFollowingForUser(
                $currentUser,
                $user,
                $userIds,
                $lastValue,
                $limit
            );
        } else {
            list($following, $lastValue) = $this->followRepository->findFollowingForUser(
                $currentUser,
                $user,
                $userIds,
                $lastValue,
                $limit
            );
        }
        
        $following = array_map('array_values', $following);

        $response = [];
        foreach ($following as list($followUser, $isFollower, $isFollowing)) {
            $response[] = new UserInfoWithFollowingData($followUser->user, $isFollowing, $isFollower);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get user followers",
     *     summary="Get user followers",
     *     tags={"User", "Following"},
     *     @SWG\Parameter(
     *          in="query",
     *          name="pendingOnly",
     *          type="boolean",
     *          description="Whether or not to return pending followers only"
     *     ),
     *     @SWG\Parameter(
     *          in="query",
     *          name="mutualOnly",
     *          type="boolean",
     *          description="Whether or not to return mutual followers only"
     *     ),
     *     @SWG\Parameter(in="query", name="search", type="string", required=false, description="Search query"),
     *     @SWG\Response(response="200", description="Get users response")
     * )
     * @ListResponse(
     *     pagination=true,
     *     paginationByLastValue=true,
     *     entityClass=UserInfoWithFollowingData::class,
     *     enableOrderBy=false,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found"}
     *     }
     * )
     * @Route("/{id}/followers", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function followers(
        UserElasticRepository $userElasticRepository,
        Request $request,
        int $id
    ): JsonResponse {
        $currentUser = $this->getUser();

        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $lastValue = $request->query->getInt('lastValue');
        $limit = $request->query->getInt('limit', 20);

        $searchQuery = mb_strtolower($request->query->get('search'));

        $userIds = null;
        if ($searchQuery) {
            [$userIds] = $userElasticRepository->findIdsByQuery($searchQuery);
        }

        if ($request->query->getBoolean('mutualOnly')) {
            list($followers, $lastValue) = $this->followRepository->findFriendsForUser(
                $currentUser,
                $user,
                $userIds,
                $lastValue,
                $limit
            );
        } elseif ($request->query->getBoolean('pendingOnly')) {
            list($followers, $lastValue) = $this->followRepository->findPendingFollowersForUser(
                $currentUser,
                $user,
                $userIds,
                $lastValue,
                $limit
            );
        } else {
            list($followers, $lastValue) = $this->followRepository->findFollowersForUser(
                $currentUser,
                $user,
                $userIds,
                $lastValue,
                $limit
            );
        }

        $followers = array_map('array_values', $followers);

        $response = [];
        foreach ($followers as list($follower, $isFollower, $isFollowing)) {
            $response[] = new UserInfoWithFollowingData($follower->follower, $isFollowing, $isFollower);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get recommended for following by contacts",
     *     summary="Get recommended users for following by contacts",
     *     tags={"User", "Following"},
     *     @SWG\Response(response="200", description="Get users response")
     * )
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=UserInfoResponse::class)
     * @Route("/recommended/contacts", methods={"GET"})
     */
    public function recommendedContacts(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if ($user->lockContactsUpload > time()) {
            return $this->createErrorResponse(ErrorCode::V1_CONTACT_PHONE_NOT_READY_YET, Response::HTTP_LOCKED);
        }

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);

        list($recommended, $lastValue) = $this->followRepository->findRecommendedFollowingByContacts(
            $user,
            $lastValue,
            $limit
        );
        $response = array_map(fn(User $u) => new UserInfoResponse($u), $recommended);

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get recommended for following by similar interests",
     *     summary="Get recommended users for following by similar interests",
     *     tags={"User", "Following"},
     *     @SWG\Response(response="200", description="Get users response")
     * )
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=UserInfoResponse::class)
     * @Route("/recommended/similar", methods={"GET"})
     */
    public function recommended(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);

        list($recommended, $lastValue) = $this->followRepository->findRecommendedFollowing(
            $user,
            $lastValue,
            $limit
        );
        $response = array_map(fn(User $u) => new UserInfoResponse($u), $recommended);

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get recommended for following by similar interests for already verified users",
     *     summary="Get recommended users for following by similar interests for already verified users",
     *     tags={"User", "Following"},
     *     @SWG\Response(response="200", description="Get users response")
     * )
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=UserInfoResponse::class)
     * @Route("/recommended", methods={"GET"})
     */
    public function recommendation(
        Request $request,
        MatchingClient $matchingClient,
        LoggerInterface $logger
    ): JsonResponse {
        return $this->handleResponse(new PaginatedResponse([], true));

        //@phpstan-ignore-next-line
        $user = $this->getUser();

        $lastValue = $request->query->get('lastValue');
        $limit = $request->query->getInt('limit', 20);

        try {
            if (empty($_ENV['PEOPLE_MATCHING_URL'])) {
                throw new RuntimeException('Matching service temporary disabled');
            }

            $responseFromPeopleMatchingClient = $matchingClient->findPeopleMatchingForUser($user, $limit, $lastValue);

            return $this->handleResponse(
                new PaginatedResponse(
                    $responseFromPeopleMatchingClient['data'] ?? [],
                    $responseFromPeopleMatchingClient['lastValue'] ?? null
                )
            );
        } catch (Exception $exception) {
            if (!$exception instanceof RuntimeException) {
                $logger->error($exception, ['exception' => $exception, 'recovered' => !$lastValue]);
            }

            //Is first page
            if (!$lastValue) {
                list($result, $lastValue) = $this->followRepository->findRecommendedFollowingByContactsAndInterests(
                    $user,
                    $lastValue,
                    $limit
                );
                $response = array_map(fn(User $u) => new UserInfoResponse($u), $result);

                return $this->handleResponse(new PaginatedResponse($response, $lastValue));
            }

            throw $exception;
        }
    }

    /**
     * @SWG\Get(
     *     description="Get friends",
     *     summary="Get friends",
     *     tags={"User", "Following"},
     *     @SWG\Parameter(in="query", name="forPingInVideoRoom", type="string", required=false),
     *     @SWG\Response(response="200", description="Get users response"),
     *     @SWG\Parameter(in="query", name="search", type="string", required=false, description="Search query")
     * )
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=FriendInfoResponse::class)
     * @Route("/friends", methods={"GET"})
     */
    public function friends(
        Request $request,
        VideoRoomRepository $videoRoomRepository,
        UserElasticRepository $userElasticRepository,
        ClubInviteRepository $clubInviteRepository
    ): JsonResponse {
        $user = $this->getUser();

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);

        $videoRoom = null;
        if ($forPingInVideoRoom = $request->query->get('forPingInVideoRoom')) {
            $videoRoom = $videoRoomRepository->findOneByName($forPingInVideoRoom);
        }

        $searchQuery = $request->query->get('search');

        if ($searchQuery) {
            [$userIds] = $userElasticRepository->findIdsByQuery($searchQuery);
        } else {
            $userIds = null;
        }

        $forInviteClub = $request->get('forInviteClub');

        list($friends, $lastValue, $totalCount) = $this->followRepository->findFriendsFollowers(
            $user,
            $videoRoom,
            $lastValue,
            $limit,
            $userIds,
            $forInviteClub && Uuid::isValid($forInviteClub) ? $forInviteClub : null
        );

        $clubInviteData = [];
        if ($forInviteClub && Uuid::isValid($forInviteClub)) {
            $friendsIds = array_map(fn(User $u) => $u->id, $friends);

            $data = $clubInviteRepository->findClubInvites(
                $user,
                $forInviteClub,
                $friendsIds
            );
            $clubInviteData = array_flip($data);
        }

        $response = [];
        foreach ($friends as $friend) {
            $item = new FriendInfoResponse($friend);
            if (isset($clubInviteData[$friend->id])) {
                $item->alreadyInvitedToClub = true;
            }
            $response[] = $item;
        }

        return $this->handleResponse(new PaginatedResponseWithCount($response, $lastValue, $totalCount));
    }

    /**
     * @SWG\Get(
     *     description="Get follow by users with user short info",
     *     summary="Get follow by users with user short info",
     *     tags={"User", "Following"},
     *     @SWG\Response(response="200", description="Get users response")
     * )
     * @ViewResponse(entityClass=FollowedByShortInfoResponse::class)
     * @Route("/{id}/followed-by/short", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function followedByShortInfo(int $id): JsonResponse
    {
        $targetUser = $this->userRepository->find($id);
        if (!$targetUser) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();

        list($followedBy, $lastValue, $count) = $this->followRepository->findFollowedByBetweenUsers(
            $currentUser,
            $targetUser,
            0,
            3
        );

        return $this->handleResponse(new FollowedByShortInfoResponse($followedBy, $count));
    }

    /**
     * @SWG\Get(
     *     description="Get follow by users with user",
     *     summary="Get follow by users with user",
     *     tags={"User", "Following"},
     *     @SWG\Response(response="200", description="Get users response")
     * )
     * @ListResponse(pagination=true, paginationByLastValue=true, entityClass=UserInfoWithFollowingData::class)
     * @Route("/{id}/followed-by", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function followedBy(Request $request, int $id): JsonResponse
    {
        $targetUser = $this->userRepository->find($id);
        if (!$targetUser) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);

        list($result, $lastValue) = $this->followRepository->findFollowedByBetweenUsers(
            $currentUser,
            $targetUser,
            $lastValue,
            $limit
        );
        $result = array_map('array_values', $result);

        $response = [];
        foreach ($result as list($followedBy, $isFollower, $isFollowing)) {
            $response[] = new UserInfoWithFollowingData($followedBy, $isFollowing, $isFollower);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get mutual friends of current and specified user",
     *     tags={"User", "Following"},
     *     @SWG\Response(response="200", description="Get users response"),
     *     @SWG\Response(response="404", description="User not found"),
     *     @SWG\Parameter(
     *         in="query",
     *         name="limit",
     *         type="integer",
     *         default="20",
     *         description="Pagination limit items per page"
     *     ),
     *     @SWG\Parameter(
     *         in="query",
     *         name="lastValue",
     *         type="integer",
     *         default="0",
     *         description="Last viewed value cursor for pagination"
     *     ),
     *     produces={"application/json"},
     * )
     * @ViewResponse(entityClass=MutualFriendsPaginatedResponse::class)
     * @Route("/{id}/mutual-friends", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function mutualFriends(Request $request, int $id): JsonResponse
    {
        $targetUser = $this->userRepository->find($id);
        if (!$targetUser) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();

        $lastValue = $request->query->getInt('lastValue');
        $limit = $request->query->getInt('limit', 20);

        [$result, $lastValue, $totalCount] = $this->followRepository->findMutualFriends(
            $currentUser,
            $targetUser,
            $lastValue,
            $limit
        );

        return $this->handleResponse(new MutualFriendsPaginatedResponse($result, $lastValue, $totalCount));
    }

    /**
     * @SWG\Get(
     *     description="Returns following counters for user",
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="User not found"),
     *     produces={"application/json"},
     *     tags={"User", "Following"},
     * )
     * @ViewResponse(entityClass=CountersResponse::class)
     * @Route("/{id}/counters", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function counters(
        int $id,
        FollowRepository $followRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $user = $userRepository->find($id);
        if (!$user) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();

        return $this->handleResponse(new CountersResponse(
            $followRepository->findConnectingCountForUser($user),
            $followRepository->findFriendCountForUser($user),
            $followRepository->findMutualFriendCount($currentUser, $user),
        ));
    }
}
