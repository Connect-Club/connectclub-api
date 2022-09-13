<?php

namespace App\Controller\V1;

use App\Annotation\Lock;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\Subscription\Response as SubscriptionResponse;
use App\DTO\V1\User\FullUserInfoResponse;
use App\DTO\V1\User\PatchUserProfile;
use App\DTO\V1\User\UserBanDeleteRequest;
use App\DTO\V2\User\FullUserInfoResponse as FullUserInfoResponseV2;
use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Role;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\DTO\V1\Chat\ChatUserInfoWithCompany;
use App\Entity\UserBlock;
use App\Event\User\DeleteAccountEvent;
use App\Message\UploadUserToElasticsearchMessage;
use App\Repository\Activity\ActivityRepository;
use App\Repository\Chat\AbstractChatRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\Subscription\SubscriptionRepository;
use App\Repository\User\UserElasticRepository;
use App\Repository\UserBlockRepository;
use App\Repository\UserRepository;
use App\Service\BanManager;
use App\Service\MatchingClient;
use Exception;
use libphonenumber\PhoneNumberUtil;
use Nelmio\ApiDocBundle\Annotation\Model;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Swagger\Annotations as SWG;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * Class VideoRoomParticipantController.
 *
 * @Route("/user")
 */
class UserController extends BaseController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @SWG\Post(
     *     description="Block user",
     *     summary="Block user",
     *     @SWG\Response(response="200", description="Successfully block user"),
     *     @SWG\Response(response="404", description="User not found"),
     *     tags={"User"},
     * )
     * @Lock(code="block_user", personal=true)
     * @Route("/{id}/block", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function block(
        UserBlockRepository $userBlockRepository,
        FollowRepository $followRepository,
        ActivityRepository $activityRepository,
        int $id
    ): Response {
        $currentUser = $this->getUser();

        if (!$user = $this->userRepository->find($id)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        if ($user->equals($currentUser)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST, Response::HTTP_BAD_REQUEST);
        }

        $isFollows = $followRepository->findOneBy(['follower' => $currentUser, 'user' => $user]);
        $isFollowing = $followRepository->findOneBy(['follower' => $user, 'user' => $currentUser]);

        $userBlock = $userBlockRepository->findActualUserBlock($currentUser, $user);
        if (!$userBlock) {
            $userBlock = new UserBlock(
                $currentUser,
                $user,
                $isFollowing !== null,
                $isFollows !== null
            );
            $userBlockRepository->save($userBlock);

            if ($isFollows) {
                $followRepository->remove($isFollows);
            }

            if ($isFollowing) {
                $followRepository->remove($isFollowing);
            }

            $activityRepository->deleteActivitiesWithUserForUser($currentUser, $user);
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Unblock user",
     *     summary="Unblock user",
     *     @SWG\Response(response="200", description="Successfully block user"),
     *     @SWG\Response(response="404", description="User not found"),
     *     tags={"User"}
     * )
     * @Lock(code="unblock_user", personal=true)
     * @Route("/{id}/unblock", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function unblock(UserBlockRepository $userBlockRepository, int $id): Response
    {
        $currentUser = $this->getUser();

        if (!$user = $this->userRepository->find($id)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $userBlock = $userBlockRepository->findActualUserBlock($currentUser, $user);
        if ($userBlock) {
            $userBlock->deletedAt = time();
            $userBlockRepository->save($userBlock);
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get (
     *     produces={"application/json"},
     *     description="Get user info",
     *     summary="Get user info",
     *     @SWG\Response(response="200", description="Info about user"),
     *     tags={"User"}
     * )
     * @Nelmio\Security(name="oauth2BearerToken")
     * @ListResponse(entityClass=UserInfoResponse::class)
     * @Route("/{username}/info", methods={"GET"})
     */
    public function infoByUsername(string $username): JsonResponse
    {
        if (!$user = $this->userRepository->findOneBy(['username' => $username])) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->handleResponse(new UserInfoResponse($user));
    }

    /**
     * @SWG\Get (
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
     * @Nelmio\Security(name="oauth2BearerToken")
     * @ListResponse(entityClass=FullUserInfoResponse::class, pagination=true, paginationByLastValue=true)
     * @Route("", methods={"GET"})
     */
    public function all(
        Request $request,
        EntityManagerInterface $entityManager
    ) : JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_UNITY_SERVER')) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if ($entityManager->getFilters()->isEnabled('softdeleteable')) {
            $entityManager->getFilters()->disable('softdeleteable');
        }

        $query = $this->userRepository->createQueryBuilder('e')
                                ->addSelect('i')
                                ->addSelect('i2')
                                ->addSelect('c1')
                                ->addSelect('c2')
                                ->addSelect('a')
                                ->leftJoin('e.accessTokens', 'a')
                                ->leftJoin('e.invite', 'i')
                                ->leftJoin('e.city', 'c1')
                                ->leftJoin('c1.country', 'c2')
                                ->leftJoin('e.interests', 'i2')
                                ->where('e.deleted IS NULL');

        $this->handleFilters(User::class, $request, $query);
        list($users, $lastValue) = $this->paginateByLastCursor($query, $request, 'id', 'DESC');

        $usersInfoResponses = array_map(fn(User $user) => new FullUserInfoResponse($user), $users);

        if (!$entityManager->getFilters()->isEnabled('softdeleteable')) {
            $entityManager->getFilters()->enable('softdeleteable');
        }

        return $this->handleResponse(new PaginatedResponse($usersInfoResponses, $lastValue));
    }

    /**
     * @SWG\Delete(
     *     produces={"application/json"},
     *     description="Delete user",
     *     summary="Delete user",
     *     @SWG\Response(response="200", description="Successfully delete user"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     tags={"User"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=UserBanDeleteRequest::class)))
     * )
     * @ViewResponse()
     * @Nelmio\Security(name="oauth2BearerToken")
     * @Route("/{id}", methods={"DELETE"}, requirements={"id": "\d+"})
     */
    public function delete(
        int $id,
        EventDispatcherInterface $dispatcher,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        Request $request
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if (!$user = $this->userRepository->find($id)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        /** @var UserBanDeleteRequest $userBanDeleteRequest */
        $userBanDeleteRequest = $this->getEntityFromRequestTo($request, UserBanDeleteRequest::class);

        if ($_ENV['STAGE'] == 1 && $userBanDeleteRequest->cleanup === true) {
            $user->invite->phoneNumber = $user->phone = PhoneNumberUtil::getInstance()->parse('+70000000000');
            $entityManager->persist($user);
            $entityManager->persist($user->invite);
            $entityManager->flush();
        }

        $user->wallet = null;
        $user->deletedBy = $this->getUser();
        $user->deleteComment = $userBanDeleteRequest->comment;
        $dispatcher->dispatch(new DeleteAccountEvent($user));

        $logger->warning(sprintf('Admin %s remove user %s', $this->getUser()->getId(), $id));

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Global ban user",
     *     summary="Global ban user",
     *     @SWG\Response(response="200", description="Successfully ban user"),
     *     @SWG\Response(response="404", description="User not found"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     tags={"User"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=UserBanDeleteRequest::class)))
     * )
     * @Route("/{id}/ban", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function ban(Request $request, BanManager $banManager, int $id): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if (!$user = $this->userRepository->find($id)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        /** @var UserBanDeleteRequest $userBanDeleteRequest */
        $userBanDeleteRequest = $this->getEntityFromRequestTo($request, UserBanDeleteRequest::class);

        $banManager->createBanUserTransactions($this->getUser(), $user, $userBanDeleteRequest->comment)->run();

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Global unban user",
     *     summary="Global unban user",
     *     @SWG\Response(response="200", description="Successfully unban user"),
     *     @SWG\Response(response="404", description="User not found"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     tags={"User"}
     * )
     * @Route("/{id}/unban", methods={"POST"}, requirements={"id": "\d+"})
     */
    public function unban(BanManager $banManager, int $id): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if (!$user = $this->userRepository->find($id)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $banManager->createUnbanUserTransactions($user)->run();

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get(
     *     description="Search users globally",
     *     summary="Search users globally",
     *     @SWG\Response(response="200", description="OK"),
     *     tags={"User"},
     *     @SWG\Parameter(in="query", name="search", type="string", required=false, description="Search query"),
     * )
     * @ListResponse(entityClass=UserInfoResponse::class, pagination=true, paginationByLastValue=true)
     * @Route("/search", methods={"GET"})
     */
    public function search(
        Request $request,
        FollowRepository $followRepository,
        UserElasticRepository $userElasticRepository,
        MatchingClient $matchingClient,
        LoggerInterface $logger
    ): JsonResponse {
        $lastValue = $request->query->get('lastValue');
        $limit = $request->query->getInt('limit', 25);
        $query = $request->query->get('search');

        if (!$query) {
            $user = $this->getUser();

            try {
                if (empty($_ENV['PEOPLE_MATCHING_URL'])) {
                    throw new RuntimeException('Matching service temporary disabled');
                }

                $responseFromPeopleMatchingClient = $matchingClient->findPeopleMatchingForUser(
                    $user,
                    $limit,
                    $lastValue
                );

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
                    list($result, $lastValue) = $followRepository->findRecommendedFollowingByContactsAndInterests(
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

        $currentUser = $this->getUser();

        [$usersIds, $lastValue] = $userElasticRepository->findIdsByQuery($query, $lastValue, $limit);

        $usersWithFollowingData = $this->userRepository->findUsersByIdsWithFollowingData($currentUser, $usersIds);

        $priorityUserIdsSorting = array_flip($usersIds);

        $result = [];
        foreach ($usersWithFollowingData as list($user, $isFollower, $isFollowing, $followers, $following)) {
            $result[$user->id] = new FullUserInfoResponseV2($user, $isFollowing, $isFollower, $followers, $following);
        }

        uksort(
            $result,
            /** @phpstan-ignore-next-line */
            fn($userIdKeyA, $userIdKeyB) => $priorityUserIdsSorting[$userIdKeyA] > $priorityUserIdsSorting[$userIdKeyB]
        );

        return $this->handleResponse(new PaginatedResponse(array_values($result), $lastValue));
    }

    /**
     * @SWG\Patch(
     *     description="Patch user",
     *     summary="Patch user",
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=PatchUserProfile::class))),
     *     @SWG\Response(response="200", description="OK"),
     *     tags={"User"}
     * )
     * @ViewResponse(
     *     entityClass=FullUserInfoResponseV2::class,
     *     errorCodesMap={Response::HTTP_NOT_FOUND, ErrorCode::V1_ERROR_NOT_FOUND, "User not found"},
     * )
     * @Route("/{id}", methods={"PATCH"}, requirements={"id": "\d+"})
     */
    public function updateUser(
        Request $request,
        int $id,
        MessageBusInterface $bus
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND);
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND);
        }

        /** @var PatchUserProfile $updateRequest */
        $updateRequest = $this->getEntityFromRequestTo($request, PatchUserProfile::class);

        $errors = $this->validate($updateRequest);
        if ($errors->count() > 0) {
            return $this->handleErrorResponse($errors);
        }

        if ($updateRequest->name !== null) {
            $user->name = (string) $updateRequest->name;
        }

        if ($updateRequest->about !== null) {
            $user->about = (string) $updateRequest->about;
        }

        if ($updateRequest->longBio !== null) {
            $user->longBio = (string) $updateRequest->longBio;
        }

        if ($updateRequest->surname !== null) {
            $user->surname = (string) $updateRequest->surname;
        }

        if ($updateRequest->countInvites !== null) {
            $user->freeInvites = (int) $updateRequest->countInvites;
        }

        if ($updateRequest->badges !== null) {
            $user->badges = $updateRequest->badges;
        }

        if ($updateRequest->shortBio !== null) {
            $user->shortBio = $updateRequest->shortBio;
        }

        if ($updateRequest->username !== null) {
            $usernameAlreadyExist = $this->userRepository
                ->createQueryBuilder('u')
                ->where('LOWER(u.username) = :username')
                ->andWhere('u.id != :id')
                ->setMaxResults(1)
                ->setFirstResult(0)
                ->getQuery()
                ->setParameter('username', $updateRequest->username)
                ->setParameter('id', $user->id)
                ->getOneOrNullResult();

            if ($usernameAlreadyExist) {
                return $this->createErrorResponse(ErrorCode::V1_USER_USERNAME_ALREADY_EXISTS);
            }

            $user->username = $updateRequest->username;
        }

        if ($updateRequest->isSuperCreator !== null) {
            if ($updateRequest->isSuperCreator) {
                $user->addRole(Role::ROLE_SUPERCREATOR);
            } else {
                $user->removeRole(Role::ROLE_SUPERCREATOR);
            }
        }

        $this->userRepository->save($user);
        $bus->dispatch(new UploadUserToElasticsearchMessage($user));

        return $this->handleResponse(new FullUserInfoResponseV2($user, false, false, 0, 0));
    }
}
