<?php

namespace App\Controller\V1\Club;

use App\Annotation\Lock;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Club\ClubEventScheduleResponse;
use App\DTO\V1\Club\ClubFullResponse;
use App\DTO\V1\Club\ClubJoinRequestForModerationResponse;
use App\DTO\V1\Club\ClubMemberResponse;
use App\DTO\V1\Club\ClubResponse;
use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V1\Club\CreateClubRequest;
use App\DTO\V1\Club\InvitationLinkResponse;
use App\DTO\V1\Club\JoinRequestWithRoleResponse;
use App\DTO\V1\Club\UpdateClubRequest;
use App\DTO\V1\Club\CurrentUserJoinRequestResponse;
use App\DTO\V1\Club\JoinRequestResponse;
use App\DTO\V1\Interests\InterestDTO;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\PaginatedResponseWithCount;
use App\DTO\V2\User\UserInfoWithFollowingData;
use App\Entity\Activity\Activity;
use App\Entity\Activity\NewClubInviteActivity;
use App\Entity\Activity\NewJoinRequestActivity;
use App\Entity\Club\Club;
use App\Entity\Club\ClubInvite;
use App\Entity\Club\ClubParticipant;
use App\Entity\Club\JoinRequest;
use App\Entity\User;
use App\Exception\Club\UserAlreadyJoinedToClubException;
use App\Message\InviteAllNetworkToClubMessage;
use App\Repository\Activity\NewClubInviteActivityRepository;
use App\Repository\Activity\NewJoinRequestActivityRepository;
use App\Repository\Club\ClubInviteRepository;
use App\Repository\Club\ClubParticipantRepository;
use App\Repository\Club\ClubRepository;
use App\Repository\Club\ClubTokenRepository;
use App\Repository\Club\JoinRequestRepository;
use App\Repository\Event\EventScheduleRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\Interest\InterestRepository;
use App\Repository\Photo\ImageRepository;
use App\Repository\User\UserElasticRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ClubVoter;
use App\Service\ActivityManager;
use App\Service\ClubManager;
use App\Service\MatchingClient;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Model;
use Parsedown;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/** @Route("/club") */
class ClubController extends BaseController
{
    private MatchingClient $matchingClient;
    private ClubRepository $clubRepository;
    private ClubParticipantRepository $clubParticipantRepository;
    private JoinRequestRepository $joinRequestRepository;
    private EventScheduleRepository $eventScheduleRepository;

    public function __construct(
        MatchingClient $matchingClient,
        ClubRepository $clubRepository,
        ClubParticipantRepository $clubParticipantRepository,
        JoinRequestRepository $joinRequestRepository,
        EventScheduleRepository $eventScheduleRepository
    ) {
        $this->matchingClient = $matchingClient;
        $this->clubRepository = $clubRepository;
        $this->clubParticipantRepository = $clubParticipantRepository;
        $this->joinRequestRepository = $joinRequestRepository;
        $this->eventScheduleRepository = $eventScheduleRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get my clubs",
     *     summary="Get my clubs",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ClubSlimResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     * @Route("/my", methods={"GET"})
     */
    public function my(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->getInt('lastValue');

        [$myClubs, $lastValue] = $this->clubRepository->findMyClubs($this->getUser(), $limit, $lastValue);

        $response = [];
        foreach ($myClubs as $club) {
            $response[] = new ClubSlimResponse($club);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Explore clubs",
     *     summary="Explore clubs",
     *     tags={"Club"},
     *     @SWG\Parameter(in="query", type="string", name="search", required=false),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ClubFullResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     * @Route("/explore", methods={"GET"})
     */
    public function explore(Request $request, MatchingClient $matchingClient, LoggerInterface $logger): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->get('lastValue');
        $search = $request->query->get('search');

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($search) {
            [$clubs, $lastValue] = $this->clubRepository->findExploredClubs(
                $currentUser,
                $limit,
                (int) $lastValue,
                $search
            );
            $clubIds = array_map(fn(array $clubRow) => $clubRow[0]->id->toString(), $clubs);
        } else {
            try {
                $matchedClubs = $matchingClient->findClubMatchingForUser($currentUser, $limit, $lastValue);

                $clubIds = array_filter(
                    array_map(
                        fn(array $club) => $club['id'],
                        $matchedClubs['data'] ?? []
                    ),
                    fn($uuid) => Uuid::isValid($uuid)
                );

                $clubs = $this->clubRepository->findClubsByIdsForUser($currentUser, $clubIds);

                $sortedClubs = [];
                foreach ($clubIds as $clubId) {
                    foreach ($clubs as $clubData) {
                        /** @var Club $club */
                        $club = $clubData[0];
                        if ($club->id->toString() == $clubId) {
                            $sortedClubs[] = $clubData;
                        }
                    }
                }
                $clubs = $sortedClubs;

                $lastValue = $matchedClubs['lastValue'] ?? null;
            } catch (Throwable $exception) {
                if ($lastValue !== null) {
                    throw $exception;
                }

                $logger->error($exception, ['exception' => $exception]);

                [$clubs, $lastValue] = $this->clubRepository->findExploredClubs(
                    $currentUser,
                    $limit,
                    (int) $lastValue,
                    $search
                );

                $clubIds = array_map(fn(array $clubRow) => $clubRow[0]->id->toString(), $clubs);
            }
        }

        /** @var JoinRequest[] $joinRequests */
        $joinRequests = $this->joinRequestRepository->findJoinRequestsOfUserOfClubIds($currentUser, $clubIds);

        $joinRequests = array_combine(
            array_map(fn(JoinRequest $joinRequest) => $joinRequest->club->id->toString(), $joinRequests),
            array_map(fn(JoinRequest $joinRequest) => $joinRequest, $joinRequests),
        );

        $response = [];
        foreach ($clubs as [0 => $club, 'status' => $status, 'cnt' => $count]) {
            $response[] = new ClubFullResponse($club, $status, $count, $joinRequests[$club->id->toString()] ?? null);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get club events",
     *     summary="Get club events",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ClubEventScheduleResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @Route("/{id}/events", methods={"GET"})
     */
    public function events(Request $request, string $id): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->getInt('lastValue');

        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$club = $this->clubRepository->find($id)) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        [$eventSchedules, $lastValue] = $this->eventScheduleRepository->findEventSchedulesForClub(
            $club,
            $limit,
            $lastValue
        );

        $response = [];
        foreach ($eventSchedules as $eventSchedule) {
            $response[] = new ClubEventScheduleResponse($eventSchedule);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get relevant clubs",
     *     summary="Get relevant clubs",
     *     tags={"Club"},
     *     @SWG\Parameter(type="string", name="query", description="Query search for clubs (not working)", in="query"),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ClubFullResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @Route("/relevant", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function relevant(Request $request, MatchingClient $matchingClient): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->get('lastValue');
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        try {
            $matchedClubs = $matchingClient->findClubMatchingForUser($currentUser, $limit, $lastValue);

            $clubIds = array_filter(
                array_map(
                    fn(array $club) => $club['id'],
                    $matchedClubs['data'] ?? []
                ),
                fn($uuid) => Uuid::isValid($uuid)
            );

            $relevantClubs = $this->clubRepository->findClubsByIdsForUser($currentUser, $clubIds);

            $sortedClubs = [];
            foreach ($clubIds as $clubId) {
                foreach ($relevantClubs as $clubData) {
                    /** @var Club $club */
                    $club = $clubData[0];
                    if ($club->id->toString() == $clubId) {
                        $sortedClubs[] = $clubData;
                    }
                }
            }
            $relevantClubs = $sortedClubs;

            $lastValue = $matchedClubs['response']['lastValue'] ?? null;
        } catch (Exception $exception) {
            [$relevantClubs, $lastValue] = $this->clubRepository->findRelevantClubs(
                $this->getUser(),
                $limit,
                (int) $lastValue
            );
        } catch (Throwable $exception) {
            throw $exception;
        }

        $response = [];
        foreach ($relevantClubs as [
                 0 => $club,
                 'status' => $role,
                 'cnt' => $participantCount
        ]) {
            $response[] = new ClubFullResponse($club, $role, $participantCount);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="List all current user join requests",
     *     summary="List all current user join requests",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ClubJoinRequestForModerationResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @Route("/join-requests", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function currentUserJoinRequests(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();

        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->getInt('lastValue');

        [$requests, $lastValue] = $this->joinRequestRepository->findForCurrentUser(
            $currentUser,
            $lastValue,
            $limit
        );

        $response = array_map(
            fn (JoinRequest $joinRequest) => new CurrentUserJoinRequestResponse($joinRequest),
            $requests
        );

        return $this->handleResponse(new PaginatedResponse(
            $response,
            $lastValue
        ));
    }

    /**
     * @SWG\Patch(
     *     description="Update a club",
     *     summary="Update a club",
     *     tags={"Club"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=UpdateClubRequest::class))),
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="422", description="Validation failed")
     * )
     * @ViewResponse(entityClass=ClubFullResponse::class)
     * @Route("/{id}", methods={"PATCH"})
     */
    public function update(
        string $id,
        Request $request,
        ImageRepository $imageRepository,
        InterestRepository $interestRepository
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $club = $this->clubRepository->find($id);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $access = $club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) ||
                  $club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER) ||
                  $this->isGranted('ROLE_ADMIN');

        if (!$access) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var UpdateClubRequest $updateClubRequest */
        $updateClubRequest = $this->getEntityFromRequestTo($request, UpdateClubRequest::class);
        $errors = $this->validate($updateClubRequest);
        if ($errors->count() > 0) {
            return $this->handleErrorResponse($errors);
        }

        if ($updateClubRequest->isPublic !== null) {
            if ($updateClubRequest->isPublic && !$club->togglePublicModeEnabled) {
                return $this->createErrorResponse('api.v1.club.cannot_set_public', Response::HTTP_PRECONDITION_FAILED);
            }

            if ($club->isPublic != $updateClubRequest->isPublic) {
                $club->isPublic = $updateClubRequest->isPublic;

                $this->matchingClient->publishEvent(
                    'clubSetPublic',
                    $currentUser,
                    ['clubId' => $club->id->toString(), 'isPrivate' => !$club->isPublic]
                );
            }
        }

        if ($updateClubRequest->description !== null) {
            $club->description = $updateClubRequest->description;
        }

        if ($updateClubRequest->title !== null) {
            $club->title = $updateClubRequest->title;
        }

        if ($updateClubRequest->imageId !== null) {
            if (!$image = $imageRepository->find($updateClubRequest->imageId)) {
                return $this->createErrorResponse(ErrorCode::V1_CLUB_IMAGE_NOT_FOUND);
            }
            $club->avatar = $image;
        }

        $totalInterestIds = [];
        $interests = $updateClubRequest->interests;
        if ($interests !== null) {
            $ids = array_unique(array_map(fn(InterestDTO $interestDTO) => $interestDTO->id, $interests));

            $club->interests->clear();

            foreach ($interestRepository->findByIds($ids, false) as $interest) {
                $club->addInterest($interest);
                $totalInterestIds[] = $interest->id;
            }
        }

        $this->clubRepository->save($club);

        $this->matchingClient->publishEvent(
            'clubInterestChange',
            $currentUser,
            ['clubId' => $club->id->toString(), 'interests' => $totalInterestIds]
        );

        return $this->handleResponse(new ClubResponse($club));
    }

    /**
     * @SWG\Post(
     *     description="Add new club",
     *     summary="Add new club",
     *     tags={"Club"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateClubRequest::class))),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=ClubFullResponse::class)
     * @Route("", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function create(
        Request $request,
        ImageRepository $imageRepository,
        UserRepository $userRepository,
        InterestRepository $interestRepository
    ): JsonResponse {
        /** @var CreateClubRequest $createClubRequest */
        $createClubRequest = $this->getEntityFromRequestTo($request, CreateClubRequest::class);
        $this->unprocessableUnlessValid($createClubRequest);

        $currentUser = $this->getUser();

        $owner = null;
        if ($createClubRequest->ownerId !== null && $this->isGranted('ROLE_ADMIN')) {
            $owner = $userRepository->find($createClubRequest->ownerId);
        }
        $owner ??= $currentUser;

        $title = trim($createClubRequest->title);

        $parseMarkdown = new Parsedown();
        $descriptionWithoutMarkdown = strip_tags($parseMarkdown->text($createClubRequest->description));
        if ($descriptionWithoutMarkdown > 450) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_DESCRIPTION_MAX_LENGTH);
        }

        if ($this->clubRepository->findOneBy(['title' => $title])) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_DESCRIPTION_TITLE_ALREADY_EXISTS);
        }

        $club = new Club($owner, $createClubRequest->title);
        $club->slug = !empty($club->slug) ? $club->slug : $club->id;
        if ($this->clubRepository->findOneBy(['slug' => $club->slug])) {
            $club->slug .= '-'.uniqid();
        }
        $club->description = $createClubRequest->description;

        if ($createClubRequest->imageId !== null) {
            if (!$image = $imageRepository->find($createClubRequest->imageId)) {
                return $this->createErrorResponse(ErrorCode::V1_CLUB_IMAGE_NOT_FOUND);
            }
            $club->avatar = $image;
        }

        $interests = $createClubRequest->interests;
        $totalInterestIds = [];
        if ($interests !== null) {
            $ids = array_unique(array_map(fn(InterestDTO $interestDTO) => $interestDTO->id, $interests));

            foreach ($interestRepository->findByIds($ids, false) as $interest) {
                $club->addInterest($interest);
                $totalInterestIds[] = $interest->id;
            }
        }

        $this->clubRepository->save($club);

        $this->matchingClient->publishEvent(
            'userClubJoin',
            $owner,
            ['clubId' => $club->id->toString(), 'role' => ClubParticipant::ROLE_OWNER]
        );

        $this->matchingClient->publishEvent(
            'clubInterestChange',
            $owner,
            ['clubId' => $club->id->toString(), 'interests' => $totalInterestIds]
        );

        $this->matchingClient->publishEvent(
            'clubSetPublic',
            $owner,
            ['clubId' => $club->id->toString(), 'isPrivate' => !$club->isPublic]
        );

        return $this->handleResponse(new ClubResponse($club, 1));
    }

    /**
     * @SWG\Get(
     *     description="Get club info",
     *     summary="Get club info",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=ClubFullResponse::class)
     * @Route("/{idOrSlug}", methods={"GET"})
     */
    public function info(string $idOrSlug): JsonResponse
    {
        $currentUser = $this->getUser();

        //@todo remove after hotfix in mobile app with deeplink parser error will be released
        if (mb_strpos($idOrSlug, '_eventId_') !== false) {
            $idOrSlug = explode('_eventId_', $idOrSlug)[0] ?? $idOrSlug;
        }

        $club = Uuid::isValid($idOrSlug) ?
            $this->clubRepository->find($idOrSlug) :
            $this->clubRepository->findOneBy(['slug' => $idOrSlug]);

        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $role = null;
        $joinRequest = null;
        if ($currentUser) {
            [$club, $participantsCount, $role] = $this->clubRepository->findWithFullInformation(
                $currentUser,
                $club->id->toString()
            );

            if (!$club) {
                return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $joinRequest = $this->joinRequestRepository->findOneBy([
                'club' => $club,
                'author' => $currentUser,
                'status' => [JoinRequest::STATUS_MODERATION, JoinRequest::STATUS_APPROVED]
            ]);
        } else {
            $participantsCount = $this->clubParticipantRepository->findParticipantsCountForClub($club);
        }

        $previewParticipants = $this->clubParticipantRepository->findPreviewParticipantsByClub($club, 3);

        return $this->handleResponse(
            new ClubFullResponse(
                $club,
                $role,
                $participantsCount,
                $joinRequest,
                $previewParticipants
            ),
        );
    }

    /**
     * @SWG\Post(
     *     description="Join to the club",
     *     summary="Join to the club",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @Lock(code="join_club", personal=true, timeout=3)
     * @ViewResponse(entityClass=JoinRequestWithRoleResponse::class)
     * @Route("/{id}/join", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function join(
        string $id,
        EntityManagerInterface $entityManager,
        NotificationManager $notificationManager,
        ActivityManager $activityManager,
        ClubManager $clubManager,
        ClubInviteRepository $clubInviteRepository
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $club = $this->clubRepository->find($id);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        if ($club->getParticipant($currentUser)) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_JOIN_REQUEST_ALREADY_EXISTS, Response::HTTP_NOT_FOUND);
        }

        if ($this->joinRequestRepository->findModerationJoinRequest($club, $currentUser)) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_JOIN_REQUEST_ALREADY_EXISTS, Response::HTTP_NOT_FOUND);
        }

        $joinRequest = new JoinRequest($club, $currentUser);
        $entityManager->persist($joinRequest);

        $clubInvite = $clubInviteRepository->findOneBy(['club' => $club, 'user' => $currentUser]);
        if ($clubInvite) {
            $entityManager->flush();
            $clubManager->approveJoinRequest($joinRequest, $clubInvite->createdBy);

            [,,$role] = $this->clubRepository->findWithFullInformation($currentUser, $club->id->toString());

            return $this->handleResponse(new JoinRequestWithRoleResponse($role, $joinRequest), Response::HTTP_CREATED);
        }

        /** @var NewJoinRequestActivity[] $activities */
        $activities = [];
        foreach ($club->participants as $participant) {
            if (!in_array($participant->role, [ClubParticipant::ROLE_MODERATOR, ClubParticipant::ROLE_OWNER])) {
                continue;
            }

            $activity = new NewJoinRequestActivity($joinRequest, $participant->user, $joinRequest->author);
            $entityManager->persist($activity);

            $activities[] = $activity;
        }

        $entityManager->flush();

        $notificationManager->setMode(NotificationManager::MODE_BATCH);
        foreach ($activities as $activity) {
            $notificationManager->sendNotifications($activity->user, new ReactNativePushNotification(
                Activity::TYPE_NEW_JOIN_REQUEST,
                $activityManager->getActivityTitle($activity),
                $activityManager->getActivityDescription($activity),
                [
                    PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                    PushNotification::PARAMETER_SPECIFIC_KEY => Activity::TYPE_NEW_JOIN_REQUEST,
                    'joinRequestId' => $joinRequest->id->toString(),
                    'clubId' => $joinRequest->club->id->toString(),
                    PushNotification::PARAMETER_IMAGE => $currentUser->getAvatarSrc(300, 300),
                    PushNotification::PARAMETER_SECOND_IMAGE => $club->avatar ?
                                                                $club->avatar->getResizerUrl(300, 300) : null,
                ],
                [
                    '%clubTitle%' => $club->title,
                ]
            ));
        }
        $notificationManager->flushBatch();

        [,,$role] = $this->clubRepository->findWithFullInformation($currentUser, $club->id->toString());

        return $this->handleResponse(new JoinRequestWithRoleResponse($role, $joinRequest), Response::HTTP_CREATED);
    }

    /**
     * @SWG\Get(
     *     description="List join requests",
     *     summary="List join requests",
     *     tags={"Club"},
     *     @SWG\Parameter(type="string", name="search", description="Query search for users", in="query"),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ClubJoinRequestForModerationResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @Route("/{id}/join-requests", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function joinRequests(
        string $id,
        Request $request,
        UserElasticRepository $userElasticRepository
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $currentUser = $this->getUser();

        $club = $this->clubRepository->find($id);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $isOwner = $club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) ||
                   $club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER) ||
                   $this->isGranted('ROLE_ADMIN');

        if (!$isOwner) {
            return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->getInt('lastValue');
        $search = $request->query->get('search');

        $userIds = null;
        if (null !== $search) {
            [$userIds] = $userElasticRepository->findIdsByQuery($search);
        }

        [$requests, $lastValue, $count] = $this->joinRequestRepository->findJoinRequestsForClub(
            $club,
            $userIds,
            $lastValue,
            $limit
        );

        $response = array_map(
            fn(JoinRequest $joinRequest) => new ClubJoinRequestForModerationResponse($joinRequest),
            $requests
        );

        return $this->handleResponse(new PaginatedResponseWithCount(
            $response,
            $lastValue,
            $count
        ));
    }

    /**
     * @SWG\Post(
     *     description="Cancel join request by author or moderator \ owner",
     *     summary="Cancel join request by author or moderator \ owner",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @Lock(code="cancel_join_request", personal=true, timeout=3)
     * @ViewResponse(entityClass=ClubFullResponse::class)
     * @Route("/{joinRequestId}/cancel", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function cancel(
        string $joinRequestId,
        EntityManagerInterface $entityManager,
        NewJoinRequestActivityRepository $newJoinRequestActivityRepository,
        LockFactory $lockFactory
    ): JsonResponse {
        if (!Uuid::isValid($joinRequestId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $lockFactory->createLock('join_request_'.$joinRequestId)->acquire(true);

        $currentUser = $this->getUser();

        $joinRequest = $this->joinRequestRepository->find($joinRequestId);
        if (!$joinRequest) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $isOwner = $joinRequest->club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) ||
                   $joinRequest->club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER);
        $isAuthor = $joinRequest->author->equals($currentUser);

        if (!$isOwner && !$isAuthor) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if ($joinRequest->status === JoinRequest::STATUS_APPROVED) {
            return $this->handleResponse(ErrorCode::V1_BAD_REQUEST, Response::HTTP_BAD_REQUEST);
        }

        foreach ($newJoinRequestActivityRepository->findBy(['joinRequest' => $joinRequest]) as $activity) {
            $entityManager->remove($activity);
        }
        $joinRequest->status = JoinRequest::STATUS_CANCELLED;
        $entityManager->flush();

        return $this->handleResponse(new JoinRequestResponse($joinRequest));
    }

    /**
     * @SWG\Post(
     *     description="Approve join request to the club",
     *     summary="Approve join request to the club",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="400", description="No invites left")
     * )
     * @Lock(code="approve_join_request", personal=true, timeout=3)
     * @ViewResponse(
     *     entityClass=ClubFullResponse::class,
     *     errorCodesMap={ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES, Response::HTTP_BAD_REQUEST, "No invites left"}
     * )
     * @Route("/{joinRequestId}/approve", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function approve(
        string $joinRequestId,
        ClubManager $clubManager,
        LockFactory $lockFactory
    ): JsonResponse {
        if (!Uuid::isValid($joinRequestId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $lockFactory->createLock('join_request_'.$joinRequestId)->acquire(true);

        $currentUser = $this->getUser();

        $joinRequest = $this->joinRequestRepository->find($joinRequestId);
        if (!$joinRequest) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $isOwner = $joinRequest->club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) ||
                   $joinRequest->club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER);

        if (!$isOwner) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if ($joinRequest->status !== JoinRequest::STATUS_MODERATION) {
            return $this->handleResponse('join_request_'.$joinRequest->status, Response::HTTP_BAD_REQUEST);
        }

        if ($joinRequest->club->freeInvites < 1) {
            return $this->createErrorResponse(
                ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES,
                Response::HTTP_BAD_REQUEST
            );
        }

        $clubManager->approveJoinRequest($joinRequest, $currentUser);

        return $this->handleResponse(new JoinRequestResponse($joinRequest));
    }

    /**
     * @SWG\Get(
     *     description="Get all clubs",
     *     summary="Get all clubs",
     *     tags={"Club"},
     *     @SWG\Parameter(type="string", name="query", description="Query search for clubs", in="query"),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=ClubFullResponse::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationWithTotalCount=true,
     *     paginationByLastValue=true
     * )
     * @Route("", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function all(Request $request): JsonResponse
    {
        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);
        $query = $request->query->get('query');

        [$relevantClubs, $lastValue, $count] = $this->clubRepository->findAllClubs(
            $this->getUser(),
            $query,
            $limit,
            $lastValue
        );

        $response = [];
        foreach ($relevantClubs as [
            0 => $club,
            'status' => $role,
            'cnt' => $participantCount
        ]) {
            $response[] = new ClubFullResponse($club, $role, $participantCount);
        }

        return $this->handleResponse(new PaginatedResponseWithCount($response, $lastValue, $count));
    }

    /**
     * @SWG\Get(
     *     description="Get club members",
     *     tags={"Club"},
     *     @SWG\Parameter(type="string", name="search", description="Query search for users", in="query"),
     *     @SWG\Response(response="200", description="Ok"),
     *     @SWG\Response(response="404", description="Club not found")
     * )
     * @ListResponse(
     *     entityClass=UserInfoWithFollowingData::class,
     *     enableOrderBy=false,
     *     pagination=true,
     *     paginationByLastValue=true
     * )
     * @Route("/{id}/members", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function members(
        string $id,
        Request $request,
        UserElasticRepository $userElasticRepository
    ): JsonResponse {
        $user = $this->getUser();

        $club = $this->clubRepository->find($id);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $search = $request->query->get('search');
        $userIds = null;
        if (null !== $search) {
            [$userIds] = $userElasticRepository->findIdsByQuery($search);
        }

        [$members, $lastValue] = $this->clubRepository->findMembersWithFollowingData(
            $club,
            $user,
            $userIds,
            $request->query->getInt('lastValue'),
            $request->query->getInt('limit', 20)
        );

        $members = array_map('array_values', $members);

        $response = [];
        foreach ($members as [$member, $isFollower, $isFollowing, $role]) {
            $response[] = new ClubMemberResponse($member, $isFollowing, $isFollower, $role);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Post(
     *     description="Make participant moderator",
     *     summary="Make participant moderator",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     *     @SWG\Response(response="403", description="Forbidden")
     * )
     * @ViewResponse()
     * @Route("/{clubId}/{userId}/moderator", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function moderator(
        string $clubId,
        string $userId,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(ClubVoter::ASSIGN_MODERATOR, $club)) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        $participant = $this->clubParticipantRepository->findOneBy([
            'user' => [
                'id' => $userId,
            ],
            'club' => $club,
        ]);
        if (!$participant) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $participant->role = ClubParticipant::ROLE_MODERATOR;
        $entityManager->flush();

        $this->matchingClient->publishEvent(
            'userClubRoleChange',
            $participant->user,
            ['clubId' => $clubId, 'role' => ClubParticipant::ROLE_MODERATOR]
        );

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Delete(
     *     description="Remove participant moderator",
     *     summary="Remove participant moderator",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     *     @SWG\Response(response="403", description="Forbidden")
     * )
     * @ViewResponse()
     * @Route("/{clubId}/{userId}/moderator", methods={"DELETE"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function removeModerator(
        string $clubId,
        string $userId,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(ClubVoter::REVOKE_MODERATOR, $club)) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        $participant = $this->clubParticipantRepository->findOneBy([
            'user' => [
                'id' => $userId,
            ],
            'club' => $club,
        ]);
        if (!$participant) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $participant->role = ClubParticipant::ROLE_MEMBER;
        $entityManager->flush();

        $this->matchingClient->publishEvent(
            'userClubRoleChange',
            $participant->user,
            ['clubId' => $clubId, 'role' => ClubParticipant::ROLE_MEMBER]
        );

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Leave from club",
     *     summary="Leave from club",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     *     @SWG\Response(response="403", description="Forbidden")
     * )
     * @ViewResponse()
     * @Route("/{clubId}/leave", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function leave(
        string $clubId,
        EntityManagerInterface $entityManager,
        ClubInviteRepository $clubInviteRepository,
        LockFactory $lockFactory
    ): JsonResponse {
        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        if (!$lockFactory->createLock('leave_'.$clubId)->acquire()) {
            return $this->createErrorResponse('process_change_owner_already_started', Response::HTTP_LOCKED);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();

        $participant = $this->clubParticipantRepository->findOneBy([
            'user' => $currentUser,
            'club' => $club,
        ]);

        if (!$participant) {
            return $this->createErrorResponse('not_found_in_participants_club', Response::HTTP_NOT_FOUND);
        }

        if ($participant->role === ClubParticipant::ROLE_OWNER) {
            return $this->createErrorResponse('club_owner_cannot_be_removed', Response::HTTP_BAD_REQUEST);
        }

        $entityManager->remove($participant);

        $clubInvite = $clubInviteRepository->findOneBy(['club' => $club, 'user' => $currentUser]);
        if (null !== $clubInvite) {
            $entityManager->remove($clubInvite);
        }

        $entityManager->flush();

        $this->matchingClient->publishEvent(
            'userLeaveFromClub',
            $participant->user,
            ['clubId' => $clubId]
        );

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Make participant owner",
     *     summary="Make participant owner",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     *     @SWG\Response(response="403", description="Forbidden")
     * )
     * @ViewResponse()
     * @Route("/{clubId}/{userId}/owner", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function changeOwner(
        string $clubId,
        string $userId,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        LockFactory $lockFactory
    ): JsonResponse {
        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        if (!$lockFactory->createLock('change_owner_'.$clubId)->acquire()) {
            return $this->createErrorResponse('process_change_owner_already_started', Response::HTTP_LOCKED);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $user = $userRepository->findOneBy(['id' => (int) $userId, 'state' => User::STATE_VERIFIED]);
        if (!$user) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $participantOwner = $this->clubParticipantRepository->findOneBy([
            'role' => ClubParticipant::ROLE_OWNER,
            'club' => $club,
        ]);

        if ($participantOwner) {
            if ($participantOwner->user->id == $userId) {
                return $this->createErrorResponse('current_owner_equals', Response::HTTP_BAD_REQUEST);
            } else {
                $participantOwner->role = ClubParticipant::ROLE_MEMBER;
            }
        }

        $participant = $this->clubParticipantRepository->findOneBy([
            'user' => ['id' => $userId],
            'club' => $club,
        ]);

        if (!$participant) {
            return $this->createErrorResponse('new_owner_not_found_in_participants_club', Response::HTTP_NOT_FOUND);
        }

        $club->owner = $user;
        $participant->role = ClubParticipant::ROLE_OWNER;
        $entityManager->flush();

        $this->matchingClient->publishEvent(
            'userClubRoleChange',
            $participant->user,
            ['clubId' => $clubId, 'role' => ClubParticipant::ROLE_OWNER]
        );

        $this->matchingClient->publishEvent(
            'userClubRoleChange',
            $participantOwner->user,
            ['clubId' => $clubId, 'role' => ClubParticipant::ROLE_MEMBER]
        );

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Delete(
     *     description="Remove participant",
     *     summary="Remove participant",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     *     @SWG\Response(response="403", description="Forbidden")
     * )
     * @ViewResponse()
     * @Route("/{clubId}/{userId}", methods={"DELETE"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function removeParticipant(
        string $clubId,
        string $userId,
        EntityManagerInterface $entityManager,
        LockFactory $lockFactory
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER)) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        if (!$lockFactory->createLock('remove_participant_'.$clubId.'_'.$userId)->acquire()) {
            return $this->createErrorResponse('process_remove_participant_already_started', Response::HTTP_LOCKED);
        }

        $participant = $this->clubParticipantRepository->findOneBy([
            'user' => ['id' => $userId],
            'club' => $club,
        ]);

        if (!$participant) {
            return $this->createErrorResponse('participant_not_found', Response::HTTP_NOT_FOUND);
        }

        if ($participant->role == ClubParticipant::ROLE_OWNER) {
            return $this->createErrorResponse('cannot_remove_owner_from_club');
        }

        $entityManager->remove($participant);
        $entityManager->flush();

        $this->matchingClient->publishEvent(
            'userLeaveFromClub',
            $participant->user,
            ['clubId' => $clubId]
        );

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post (
     *     description="Craete invitation link",
     *     summary="Create invitation link",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     * )
     * @ViewResponse(entityClass=InvitationLinkResponse::class)
     * @Route("/{clubId}/invitation-code", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function invitationLink(
        string $clubId,
        EntityManagerInterface $entityManager,
        LockFactory $lockFactory
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER)) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $lockFactory->createLock('generate_invitation_link_'.$clubId)->acquire(true);
        $entityManager->refresh($club);

        if (!$club->invitationLink) {
            $club->invitationLink = Uuid::uuid4()->toString();
            $this->clubRepository->save($club);
        }

        return $this->handleResponse(new InvitationLinkResponse($club));
    }

    /**
     * @SWG\Post(
     *     description="Invite all users from network to the club",
     *     summary="Invite all users from network to the club",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     * )
     * @Route("/{clubId}/all", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function inviteAllUserFromNetwork(
        string $clubId,
        ClubInviteRepository $clubInviteRepository,
        FollowRepository $followRepository,
        MessageBusInterface $bus
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER)) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $friendsCount = $followRepository->findFriendCountForUser($currentUser);

        $bus->dispatch(new InviteAllNetworkToClubMessage($clubId, $currentUser->id));
        $clubInviteRepository->createTokenForMyNetwork($club, $currentUser);

        if ($friendsCount > $club->freeInvites) {
            return $this->createErrorResponseWithData(
                ErrorCode::V1_ERROR_INVITE_NO_FREE_INVITES,
                ['freeInvites' => $club->freeInvites, 'countNetwork' => $friendsCount],
                Response::HTTP_EXPECTATION_FAILED
            );
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Invite user from network to the club",
     *     summary="Invite user from network to the club",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     * )
     * @Route("/{clubId}/{userId}", methods={"POST"}, requirements={"userId":"\d+"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function inviteUserFromNetwork(
        string $clubId,
        string $userId,
        UserRepository $userRepository,
        ClubManager $clubManager
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!Uuid::isValid($clubId)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST);
        }

        $club = $this->clubRepository->find($clubId);
        if (!$club) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_MODERATOR) &&
            !$club->isParticipantRole($currentUser, ClubParticipant::ROLE_OWNER)) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$user->isHasFollower($currentUser) || !$currentUser->isHasFollower($user)) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        try {
            $clubManager->addClubInviteForUser($club, $user, $currentUser);
        } catch (UserAlreadyJoinedToClubException $alreadyJoinedToClubException) {
            return $this->createErrorResponse('user_already_joined', Response::HTTP_BAD_REQUEST);
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get(
     *     description="Get users participants",
     *     summary="Get users participants",
     *     tags={"Club"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Not found"),
     *     @SWG\Response(response="403", description="Forbidden")
     * )
     * @ViewResponse()
     * @Route("/{userId}/participant", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function participate(
        Request $request,
        UserRepository $userRepository,
        ClubParticipantRepository $clubParticipantRepository,
        string $userId
    ): JsonResponse {
        $currentUser = $this->getUser();

        $user = $userRepository->findOneBy(['id' => (int) $userId, 'state' => User::STATE_VERIFIED]);
        if (!$user) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $limit = $request->query->getInt('limit', 20);
        $lastValue = $request->query->getInt('lastValue');

        /** @var ClubParticipant[] $items */
        [$items, $lastValue] = $clubParticipantRepository->findClubParticipant($user, $limit, $lastValue);

        $clubIds = array_map(fn(ClubParticipant $p) => $p->club->id->toString(), $items);

        $clubs = $this->clubRepository->findClubsByIdsForUser($currentUser, $clubIds);

        /** @var JoinRequest[] $joinRequests */
        $joinRequests = $this->joinRequestRepository->findJoinRequestsOfUserOfClubIds($currentUser, $clubIds);

        $joinRequests = array_combine(
            array_map(fn(JoinRequest $joinRequest) => $joinRequest->club->id->toString(), $joinRequests),
            array_map(fn(JoinRequest $joinRequest) => $joinRequest, $joinRequests),
        );

        $response = [];
        foreach ($clubs as [0 => $club, 'status' => $status, 'cnt' => $count]) {
            $response[] = new ClubFullResponse($club, $status, $count, $joinRequests[$club->id->toString()] ?? null);
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }
}
