<?php

namespace App\Controller\V1\Event;

use App\Annotation\Lock;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Ethereum\SlimTokenResponse;
use App\DTO\V1\Event\CreateEventScheduleRequest;
use App\DTO\V1\Event\EventScheduleResponse;
use App\DTO\V1\Event\EventScheduleWithTokenResponse;
use App\DTO\V1\Event\UpdateEventScheduleRequest;
use App\DTO\V2\Interests\InterestDTO;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V2\User\UserInfoResponse;
use App\Entity\Activity\ApprovedPrivateMeetingActivity;
use App\Entity\Activity\ArrangedPrivateMeetingActivity;
use App\Entity\Activity\CancelledPrivateMeetingActivity;
use App\Entity\Activity\ChangedPrivateMeetingActivity;
use App\Entity\Activity\ClubRegisteredAsCoHostActivity;
use App\Entity\Activity\RegisteredAsCoHostActivity;
use App\Entity\Activity\RegisteredAsSpeakerActivity;
use App\Entity\Club\Club;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Event\EventScheduleSubscription;
use App\Entity\Event\EventToken;
use App\Entity\Event\RequestApprovePrivateMeetingChange;
use App\Entity\Interest\Interest;
use App\Entity\User;
use App\Repository\Activity\ActivityRepository;
use App\Repository\Activity\RegisteredAsCoHostActivityRepository;
use App\Repository\Club\ClubParticipantRepository;
use App\Repository\Club\ClubRepository;
use App\Repository\Club\ClubTokenRepository;
use App\Repository\Event\EventScheduleRepository;
use App\Repository\Event\EventScheduleSubscriptionRepository;
use App\Repository\Event\RequestApprovePrivateMeetingChangeRepository;
use App\Repository\EventScheduleFestivalSceneRepository;
use App\Repository\Interest\InterestRepository;
use App\Repository\User\LanguageRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Security\Voter\Event\EventScheduleVoter;
use App\Service\ActivityManager;
use App\Service\EventScheduleManager;
use App\Service\InfuraClient;
use App\Service\LanguageManager;
use App\Service\MatchingClient;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\Notification\TimeSpecificZoneTranslationParameter;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Ethereum\DataType\EthD;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use function array_values;

/**
 * @Route("/event-schedule")
 */
class EventScheduleController extends BaseController
{
    private EventScheduleRepository $eventScheduleRepository;
    private ClubParticipantRepository $clubParticipantRepository;
    private EventScheduleFestivalSceneRepository $eventScheduleFestivalSceneRepository;
    private NotificationManager $notificationManager;
    private EventScheduleManager $eventScheduleManager;

    public function __construct(
        EventScheduleRepository $eventScheduleRepository,
        ClubParticipantRepository $clubParticipantRepository,
        EventScheduleFestivalSceneRepository $eventScheduleFestivalSceneRepository,
        NotificationManager $notificationManager,
        EventScheduleManager $eventScheduleManager
    ) {
        $this->eventScheduleRepository = $eventScheduleRepository;
        $this->clubParticipantRepository = $clubParticipantRepository;
        $this->eventScheduleFestivalSceneRepository = $eventScheduleFestivalSceneRepository;
        $this->notificationManager = $notificationManager;
        $this->eventScheduleManager = $eventScheduleManager;
    }

    /**
     * @SWG\Post(
     *     description="Subscribe to event schedule",
     *     summary="Subscribe to event schedule",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @Route("/{id}/subscribe", methods={"POST"})
     * @ViewResponse()
     */
    public function subscribe(
        string $id,
        EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository
    ): JsonResponse {
        $eventSchedule = $this->eventScheduleRepository->find($id);
        if (!$eventSchedule) {
            return $this->createErrorResponse([ErrorCode::V1_ERROR_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();

        $subscription = $eventScheduleSubscriptionRepository->findOneBy([
            'eventSchedule' => $eventSchedule,
            'user' => $user
        ]);

        if (!$subscription) {
            $subscription = new EventScheduleSubscription($eventSchedule, $user);
            $eventScheduleSubscriptionRepository->save($subscription);
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Unsubscribe to event schedule",
     *     summary="Unsubscribe to event schedule",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @Route("/{id}/unsubscribe", methods={"POST"})
     * @ViewResponse()
     */
    public function unsubscribe(
        string $id,
        EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository
    ): JsonResponse {
        $eventSchedule = $this->eventScheduleRepository->find($id);
        if (!$eventSchedule) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();

        $subscription = $eventScheduleSubscriptionRepository->findOneBy([
            'eventSchedule' => $eventSchedule,
            'user' => $user
        ]);

        if ($subscription) {
            $eventScheduleSubscriptionRepository->remove($subscription);
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Create event schedule",
     *     summary="Create event schedule",
     *     tags={"Event"},
     *     @SWG\Response(response="201", description="Success"),
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateEventScheduleRequest::class)))
     * )
     * @ViewResponse(
     *     entityClass=EventScheduleResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found"},
     *         {Response::HTTP_BAD_REQUEST, ErrorCode::V1_CLUB_NOT_FOUND, "Club not found"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "title:cannot_be_empty", "Title cannot be empty"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "participants:cannot_be_empty", "Participants cannot be empty"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "date:cannot_be_empty", "Date cannot be empty"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "date:event_schedule.date_time_must_be_greater_now", "Date < now"},
     *         {Response::HTTP_UNPROCESSABLE_ENTITY, "description:cannot_be_empty", "Description cannot be empty"},
     *     }
     * )
     * @Route("", methods={"POST"})
     */
    public function create(
        Request $request,
        EntityManagerInterface $em,
        LanguageRepository $languageRepository,
        ActivityManager $activityManager,
        UserRepository $userRepository,
        LanguageManager $languageManager,
        ClubTokenRepository $clubTokenRepository,
        InterestRepository $interestRepository,
        MatchingClient $matchingClient,
        ClubRepository $clubRepository
    ): JsonResponse {
        $currentUser = $this->getUser();

        /** @var CreateEventScheduleRequest $createRequest */
        $createRequest = $this->getEntityFromRequestTo($request, CreateEventScheduleRequest::class);

        $this->unprocessableUnlessValid($createRequest);

        $specialGuestsIds = array_unique(
            array_map(fn(UserInfoResponse $u) => (int)$u->id, $createRequest->specialGuests ?? [])
        );

        $participantIds = array_unique(array_merge(
            $specialGuestsIds,
            array_map(fn(UserInfoResponse $u) => (int)$u->id, $createRequest->participants ?? [])
        ));

        $key = array_search($currentUser->id, $participantIds);
        if ($key !== false) {
            unset($participantIds[$key]);
        }

        $participants = $userRepository->findUsersByIds($participantIds);
        if (count($participants) != count($participantIds)) {
            return $this->createErrorResponse([ErrorCode::V1_USER_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $interestsIds = array_unique(array_map(fn(InterestDTO $dto) => $dto->id, $createRequest->interests));
        $interests = $interestRepository->findByIds($interestsIds, false);

        if ($languageId = $createRequest->language) {
            $language = $languageRepository->find($languageId);
            if (!$language) {
                return $this->createErrorResponse(ErrorCode::V1_LANGUAGE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $totalInterests = [];
            foreach ($interests as $interest) {
                $totalInterests[$interest->id] = $interest;
            }

            $interests = array_values($totalInterests);
        } else {
            $language = $languageManager->findLanguageByIp($request->getClientIp());
        }

        $eventSchedule = new EventSchedule(
            $currentUser,
            $createRequest->title,
            $createRequest->date,
            $createRequest->description,
            $language
        );

        if ($createRequest->forMembersOnly !== null) {
            $eventSchedule->forMembersOnly = $createRequest->forMembersOnly;
        }

        if ($createRequest->isPrivate) {
            $eventSchedule->isPrivate = true;
        }

        $club = null;
        if ($createRequest->clubId) {
            $club = $clubRepository->find($createRequest->clubId);
            if (!$club) {
                return $this->createErrorResponse(ErrorCode::V1_CLUB_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }
            $eventSchedule->club = $club;
        }

        if ($createRequest->tokenIds && $createRequest->clubId) {
            if (!$club) {
                return $this->createErrorResponse('cannot_set_token_for_not_found_club', Response::HTTP_BAD_REQUEST);
            }

            $clubTokens = $clubTokenRepository->findClubTokensForClubIdAndTokenIds(
                $eventSchedule->club,
                array_filter(
                    array_unique($createRequest->tokenIds),
                    fn($uuid) => $uuid && Uuid::isValid((string) $uuid)
                )
            );

            foreach ($clubTokens as $clubToken) {
                $eventSchedule->forOwnerTokens->add(new EventToken($eventSchedule, $clubToken->token));
                $eventSchedule->isTokensRequired = true;
            }
        }

        if ($createRequest->festivalCode || $createRequest->festivalSceneId) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
            }

            if (Uuid::isValid($createRequest->festivalSceneId)) {
                $eventSchedule->festivalScene = $this->eventScheduleFestivalSceneRepository->find(
                    $createRequest->festivalSceneId
                );
            }

            $eventSchedule->festivalCode = $createRequest->festivalCode;
            $eventSchedule->endDateTime = $createRequest->dateEnd;
        }

        foreach ($interests as $interest) {
            $eventSchedule->addInterest($interest);
        }
        $em->persist($eventSchedule);

        $em->persist(new EventScheduleParticipant($eventSchedule, $currentUser));
        if (!$eventSchedule->isPrivate) {
            $notificationParticipants = $this->eventScheduleManager->fetchParticipantsForEventSchedule($eventSchedule);
            $this->eventScheduleManager->createActivityForEventSchedule(
                $eventSchedule,
                $currentUser,
                $notificationParticipants
            );

            foreach ($participants as $participant) {
                $eventScheduleParticipant = new EventScheduleParticipant($eventSchedule, $participant);
                if (in_array($participant->id, $specialGuestsIds)) {
                    $eventScheduleParticipant->isSpecialGuest = true;
                }

                if ($eventScheduleParticipant->isSpecialGuest) {
                    $registeredAsCoHostActivity = new RegisteredAsSpeakerActivity(
                        $eventSchedule,
                        $club ?? null,
                        $participant,
                        $currentUser
                    );
                } else {
                    if ($eventSchedule->club) {
                        $registeredAsCoHostActivity = new ClubRegisteredAsCoHostActivity(
                            $eventSchedule->club,
                            $eventSchedule,
                            $participant,
                            $currentUser
                        );
                    } else {
                        $registeredAsCoHostActivity = new RegisteredAsCoHostActivity(
                            $eventSchedule,
                            $participant,
                            $currentUser
                        );
                    }
                }

                $em->persist($eventScheduleParticipant);
                $em->persist($registeredAsCoHostActivity);

                $this->notificationManager->sendNotifications(
                    $participant,
                    new ReactNativePushNotification(
                        'event-schedule',
                        $activityManager->getActivityTitle($registeredAsCoHostActivity),
                        $activityManager->getActivityDescription($registeredAsCoHostActivity),
                        [
                            'eventScheduleId' => $eventScheduleParticipant->event->id->toString(),
                            PushNotification::PARAMETER_IMAGE => $currentUser->getAvatarSrc(300, 300),
                            PushNotification::PARAMETER_SECOND_IMAGE => isset($club) && $club->avatar ?
                                $club->avatar->getResizerUrl(300, 300) :
                                null,
                        ],
                        [
                            '%displayName%' => $currentUser->getFullNameOrUsername(),
                            '%eventName%' => $eventScheduleParticipant->event->name,
                            '%time%' => new TimeSpecificZoneTranslationParameter(
                                $eventScheduleParticipant->event->dateTime,
                                'l, F d \a\t h:i A'
                            ),
                            '%clubTitle%' => $club->title ?? ''
                        ]
                    )
                );
            }
            $em->flush();
        } else {
            foreach ($participants as $participant) {
                $eventScheduleParticipant = new EventScheduleParticipant($eventSchedule, $participant);
                $em->persist($eventScheduleParticipant);
                $eventSchedule->participants->add($eventScheduleParticipant);
            }

            /** @var User[] $participantsPrivateMeeting */
            $participantsPrivateMeeting = $eventSchedule->participants->filter(
                fn(EventScheduleParticipant $p) => !$p->user->equals($currentUser)
            )->map(
                fn(EventScheduleParticipant $p) => $p->user
            )->getValues();

            if (!$participantsPrivateMeeting) {
                return $this->createErrorResponse('empty_participant_for_private_meeting', Response::HTTP_BAD_REQUEST);
            }

            $em->persist(new EventScheduleSubscription($eventSchedule, $currentUser));

            foreach ($participantsPrivateMeeting as $participant) {
                $activity = new ArrangedPrivateMeetingActivity($eventSchedule, $participant, $currentUser);
                $em->persist($activity);

                $this->notificationManager->sendNotifications(
                    $participant,
                    new ReactNativePushNotification(
                        'event-schedule',
                        $activityManager->getActivityTitle($activity),
                        $activityManager->getActivityDescription($activity),
                        [
                            'eventScheduleId' => $eventSchedule->id->toString(),
                            PushNotification::PARAMETER_IMAGE => $currentUser->getAvatarSrc(300, 300),
                            PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                            PushNotification::PARAMETER_SPECIFIC_KEY => $activity->getType(),
                        ]
                    )
                );

                $em->persist(new EventScheduleSubscription($eventSchedule, $participant));
                $em->persist(new RequestApprovePrivateMeetingChange($eventSchedule, $participant));
            }
        }

        $em->flush();

        $matchingClient->publishEventOwnedBy('userMeetingScheduled', $currentUser, [
            'id' => $eventSchedule->id->toString(),
            'interest_id' => array_map(fn(Interest $i) => $i->id, $interests),
            'is_private' => $eventSchedule->isPrivate,
        ]);

        return $this->handleResponse(
            new EventScheduleResponse(
                $eventSchedule,
                true,
                true,
                null,
                false,
                $this->getPredefinedClubParticipantsInfo($eventSchedule)
            ),
            Response::HTTP_CREATED
        );
    }

    /**
     * @Route("/{id}/approve", methods={"POST"})
     * @SWG\Post(
     *     description="Approve private event schedule",
     *     summary="Approve private event schedule",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Ok response")
     * )
     * @Lock(code="approve_private_event_schedule_meeting")
     * @ViewResponse()
     */
    public function approvePrivateMeeting(
        string $id,
        RequestApprovePrivateMeetingChangeRepository $requestApprovePrivateMeetingChangeRepository,
        EntityManagerInterface $entityManager,
        ActivityManager $activityManager
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return $this->handleResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $eventSchedule = $this->eventScheduleRepository->find($id);
        if (!$eventSchedule) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();

        $requestApprove = $requestApprovePrivateMeetingChangeRepository->findOneBy([
            'eventSchedule' => $eventSchedule,
            'user' => $currentUser,
            'reviewed' => false,
        ]);

        if (!$requestApprove) {
            return $this->createErrorResponse('not_found_requirement_for_approve_changes', Response::HTTP_BAD_REQUEST);
        }

        $requestApprove->reviewed = true;
        $requestApprovePrivateMeetingChangeRepository->add($requestApprove);

        $participants = $eventSchedule->participants->filter(
            fn(EventScheduleParticipant $p) => !$p->user->equals($currentUser)
        )->map(
            fn(EventScheduleParticipant $p) => $p->user
        )->getValues();

        foreach ($participants as $participant) {
            $activity = new ApprovedPrivateMeetingActivity($eventSchedule, $participant, $currentUser);
            $entityManager->persist($activity);

            $this->notificationManager->sendNotifications(
                $participant,
                new ReactNativePushNotification(
                    'event-schedule',
                    $activityManager->getActivityTitle($activity),
                    $activityManager->getActivityDescription($activity),
                    [
                        'eventScheduleId' => $eventSchedule->id->toString(),
                        PushNotification::PARAMETER_IMAGE => $currentUser->getAvatarSrc(300, 300),
                        PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                        PushNotification::PARAMETER_SPECIFIC_KEY => $activity->getType(),
                    ]
                )
            );
        }
        $entityManager->flush();

        return $this->handleResponse([]);
    }

    /**
     * @Route("/{id}/cancel", methods={"POST"})
     * @SWG\Post(
     *     description="Cancel private event schedule",
     *     summary="Cancel private event schedule",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Ok response")
     * )
     * @Lock(code="cancel_private_event_schedule_meeting")
     * @ViewResponse()
     */
    public function cancelPrivateMeeting(
        string $id,
        EntityManagerInterface $entityManager,
        ActivityManager $activityManager,
        MatchingClient $matchingClient
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return $this->handleResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $eventSchedule = $this->eventScheduleRepository->find($id);
        if (!$eventSchedule) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();

        $participants = $eventSchedule->participants->filter(
            fn(EventScheduleParticipant $p) => !$p->user->equals($currentUser)
        )->map(
            fn(EventScheduleParticipant $p) => $p->user
        )->getValues();

        foreach ($participants as $participant) {
            $activity = new CancelledPrivateMeetingActivity($eventSchedule, $participant, $currentUser);
            $entityManager->persist($activity);

            $this->notificationManager->sendNotifications(
                $participant,
                new ReactNativePushNotification(
                    'event-schedule',
                    $activityManager->getActivityTitle($activity),
                    $activityManager->getActivityDescription($activity),
                    [
                        'eventScheduleId' => $eventSchedule->id->toString(),
                        PushNotification::PARAMETER_IMAGE => $currentUser->getAvatarSrc(300, 300),
                        PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                        PushNotification::PARAMETER_SPECIFIC_KEY => $activity->getType(),
                    ]
                )
            );
        }
        $entityManager->flush();
        $matchingClient->publishEvent('eventScheduleRemoved', $currentUser, ['id' => $id]);

        return $this->handleResponse([]);
    }

    /**
     * @Route("/{id}", methods={"PATCH"})
     *
     * @SWG\Patch(
     *     description="Update event schedule",
     *     summary="Update event schedule",
     *     tags={"Event"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=UpdateEventScheduleRequest::class))),
     *     @SWG\Response(response="200", description="Ok response")
     * )
     *
     * @ViewResponse(
     *     entityClass=EventScheduleResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_BAD_REQUEST, ErrorCode::V1_EVENT_SCHEDULE_IS_EXPIRED, "Event schedule expired"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_ERROR_NOT_FOUND, "Event schedule not found (or removed)"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "Participant not found in friends"},
     *         {Response::HTTP_FORBIDDEN, ErrorCode::V1_ACCESS_DENIED, "Access denied (you are not co host or owner)"},
     *     }
     * )
     */
    public function update(
        EntityManagerInterface $em,
        RegisteredAsCoHostActivityRepository $registeredAsCoHostActivityRepository,
        InterestRepository $interestRepository,
        LanguageRepository $languageRepository,
        ClubTokenRepository $clubTokenRepository,
        UserRepository $userRepository,
        Request $request,
        ActivityManager $activityManager,
        MatchingClient $matchingClient,
        RequestApprovePrivateMeetingChangeRepository $requestApprovePrivateMeetingChangeRepository,
        LockFactory $lockFactory,
        string $id
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse([ErrorCode::V1_ERROR_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $lockFactory->createLock('update_event_schedule_'.$id)->acquire(true);

        $eventSchedule = $this->eventScheduleRepository->find($id);
        if (!$eventSchedule) {
            return $this->createErrorResponse([ErrorCode::V1_ERROR_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(EventScheduleVoter::EVENT_SCHEDULE_UPDATE, $eventSchedule)) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if ($eventSchedule->videoRoom && $eventSchedule->videoRoom->doneAt) {
            return $this->createErrorResponse([ErrorCode::V1_EVENT_SCHEDULE_IS_EXPIRED], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();

        /** @var UpdateEventScheduleRequest $updateRequest */
        $updateRequest = $this->getEntityFromRequestTo($request, UpdateEventScheduleRequest::class);

        if ($updateRequest->festivalCode || $updateRequest->festivalSceneId) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
            }

            if (Uuid::isValid($updateRequest->festivalSceneId)) {
                $eventSchedule->festivalScene = $this->eventScheduleFestivalSceneRepository->find(
                    $updateRequest->festivalSceneId
                );
            }

            $eventSchedule->festivalCode = $updateRequest->festivalCode;
            $eventSchedule->endDateTime = $updateRequest->dateEnd;
        }

        $this->unprocessableUnlessValid($updateRequest);


        $eventSchedule->name = $updateRequest->title;
        $timeChanged = $updateRequest->date != $eventSchedule->dateTime;
        $eventSchedule->dateTime = $updateRequest->date;
        $eventSchedule->description = $updateRequest->description;

        if (!$eventSchedule->isPrivate) {
            $actualParticipantIds = $eventSchedule->participants
                ->filter(fn(EventScheduleParticipant $p) => !$p->isSpecialGuest)
                ->map(fn(EventScheduleParticipant $p) => $p->user->id)
                ->getValues();

            $actualSpecialGuestsIds = $eventSchedule->participants
                ->filter(fn(EventScheduleParticipant $p) => $p->isSpecialGuest)
                ->map(fn(EventScheduleParticipant $p) => $p->user->id)
                ->getValues();

            $newParticipantIds = array_unique(
                array_map(fn(UserInfoResponse $u) => (int)$u->id, $updateRequest->participants ?? [])
            );

            $newSpecialGuestsIds = array_unique(
                array_map(fn(UserInfoResponse $u) => (int)$u->id, $updateRequest->specialGuests ?? [])
            );

            $key = array_search($eventSchedule->owner->id, $newParticipantIds);
            if ($key !== false) {
                unset($newParticipantIds[$key]);
            }

            $key = array_search($eventSchedule->owner->id, $newSpecialGuestsIds);
            if ($key !== false) {
                unset($newSpecialGuestsIds[$key]);
            }

            $needRemoveParticipantIds = array_diff($actualParticipantIds, $newParticipantIds);
            $needRemoveParticipants = $eventSchedule->participants->filter(
                fn(EventScheduleParticipant $p) => in_array($p->user->id, $needRemoveParticipantIds)
            );

            $needRemoveSpecialGuestsIds = array_diff($actualSpecialGuestsIds, $newSpecialGuestsIds);
            $needRemoveSpecialGuests = $eventSchedule->participants->filter(
                fn(EventScheduleParticipant $p) => in_array($p->user->id, $needRemoveSpecialGuestsIds)
            );

            foreach ($needRemoveSpecialGuests as $needRemoveSpecialGuest) {
                $needRemoveParticipants->add($needRemoveSpecialGuest);
            }

            $needRemoveParticipants = $needRemoveParticipants->getValues();

            /** @var EventScheduleParticipant $needRemoveParticipant */
            foreach ($needRemoveParticipants as $needRemoveParticipant) {
                if ($needRemoveParticipant->user->equals($eventSchedule->owner)) {
                    continue;
                }

                $eventSchedule->participants->removeElement($needRemoveParticipant);

                $activity = $registeredAsCoHostActivityRepository->findOneBy([
                    'eventSchedule' => $eventSchedule,
                    'user' => $needRemoveParticipant->user,
                ]);

                if ($activity) {
                    $em->remove($activity);
                }
            }

            $keyCurrentUser = array_search($user->id, $newParticipantIds);
            if ($keyCurrentUser !== false) {
                unset($newParticipantIds[$keyCurrentUser]);
            }

            $eventSchedule->clearInterests();

            if ($updateRequest->language) {
                $language = $languageRepository->find($updateRequest->language);
                if (!$language) {
                    return $this->createErrorResponse(ErrorCode::V1_LANGUAGE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
                }

                $eventSchedule->language = $language;
                $eventSchedule->languages = [$language->code];
            }

            $interestsIds = array_unique(array_map(fn(InterestDTO $dto) => $dto->id, $updateRequest->interests));
            $interests = $interestRepository->findByIds($interestsIds, false);

            foreach ($interests as $interest) {
                $eventSchedule->addInterest($interest);
            }

            if ($eventSchedule->forMembersOnly && $updateRequest->forMembersOnly === false && $eventSchedule->club) {
                $notificationParticipants = $this->eventScheduleManager
                                                 ->fetchParticipantsForEventIgnoreClubParticipants($eventSchedule);

                $this->eventScheduleManager->createActivityForEventSchedule(
                    $eventSchedule,
                    $user,
                    $notificationParticipants
                );
            }

            if ($updateRequest->forMembersOnly !== null) {
                $eventSchedule->forMembersOnly = $updateRequest->forMembersOnly;
            }

            if ($eventSchedule->club !== null && $updateRequest->clubId === null) {
                $requestData = json_decode($request->getContent(), true) ?? [];
                if (isset($requestData['clubId'])) {
                    $eventSchedule->club = null;
                }
            }

            if ($updateRequest->tokenIds) {
                if ($eventSchedule->club === null) {
                    return $this->createErrorResponse('api.v1.event_schedule.club_must_be_set_for_nft_token');
                }

                $clubTokens = $clubTokenRepository->findClubTokensForClubIdAndTokenIds(
                    $eventSchedule->club,
                    array_filter(
                        array_unique($updateRequest->tokenIds),
                        fn($uuid) => $uuid && Uuid::isValid((string) $uuid)
                    )
                );

                foreach ($clubTokens as $clubToken) {
                    $eventSchedule->forOwnerTokens->add(new EventToken($eventSchedule, $clubToken->token));
                }
            } else {
                $eventSchedule->forOwnerTokens->clear();
                $eventSchedule->isTokensRequired = false;
            }

            $em->persist($eventSchedule);

            $users = $userRepository->findBy(['id' => array_merge($newParticipantIds, $newSpecialGuestsIds)]);
            foreach ($users as $participant) {
                $eventScheduleParticipantNotExists = $eventSchedule->participants->filter(
                    fn(EventScheduleParticipant $p) => $p->user->equals($participant)
                )->isEmpty();

                if (!$eventScheduleParticipantNotExists) {
                    continue;
                }

                $eventScheduleParticipant = new EventScheduleParticipant($eventSchedule, $participant);
                if (in_array($participant->id, $newSpecialGuestsIds)) {
                    $eventScheduleParticipant->isSpecialGuest = true;
                }

                $eventSchedule->participants->add($eventScheduleParticipant);

                if ($eventScheduleParticipant->isSpecialGuest) {
                    $registeredAsCoHostActivity = new RegisteredAsSpeakerActivity(
                        $eventSchedule,
                        $eventSchedule->club ?? null,
                        $participant,
                        $user
                    );
                } elseif ($eventSchedule->club) {
                    $registeredAsCoHostActivity = new ClubRegisteredAsCoHostActivity(
                        $eventSchedule->club,
                        $eventSchedule,
                        $participant,
                        $user
                    );
                } else {
                    $registeredAsCoHostActivity = new RegisteredAsCoHostActivity(
                        $eventSchedule,
                        $participant,
                        $user
                    );
                }

                $em->persist($registeredAsCoHostActivity);

                $club = $eventSchedule->club;
                $this->notificationManager->sendNotifications(
                    $participant,
                    new ReactNativePushNotification(
                        'event-schedule',
                        $activityManager->getActivityTitle($registeredAsCoHostActivity),
                        $activityManager->getActivityDescription($registeredAsCoHostActivity),
                        [
                            'eventScheduleId' => $eventScheduleParticipant->event->id->toString(),
                            PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(300, 300),
                            PushNotification::PARAMETER_SECOND_IMAGE => $club && $club->avatar ?
                                $club->avatar->getResizerUrl(300, 300) :
                                null,
                        ],
                        [
                            '%displayName%' => $user->getFullNameOrUsername(),
                            '%eventName%' => $eventScheduleParticipant->event->name,
                            '%time%' => new TimeSpecificZoneTranslationParameter(
                                $eventScheduleParticipant->event->dateTime
                            ),
                            '%clubTitle%' => $club->title ?? ''
                        ]
                    )
                );
            }
        } elseif ($timeChanged) {
            /** @var User[] $participantsPrivateMeeting */
            $participantsPrivateMeeting = $eventSchedule->participants->filter(
                fn(EventScheduleParticipant $p) => !$p->user->equals($user)
            )->map(
                fn(EventScheduleParticipant $p) => $p->user
            )->getValues();

            foreach ($participantsPrivateMeeting as $participant) {
                $requestApprove = $requestApprovePrivateMeetingChangeRepository->findOneBy([
                    'eventSchedule' => $eventSchedule,
                    'user' => $participant,
                    'reviewed' => false
                ]);

                if (!$requestApprove) {
                    $em->persist(new RequestApprovePrivateMeetingChange($eventSchedule, $participant));
                }

                $activity = new ChangedPrivateMeetingActivity($eventSchedule, $participant, $user);

                $this->notificationManager->sendNotifications(
                    $participant,
                    new ReactNativePushNotification(
                        'event-schedule',
                        $activityManager->getActivityTitle($activity),
                        $activityManager->getActivityDescription($activity),
                        [
                            'eventScheduleId' => $eventSchedule->id->toString(),
                            PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(300, 300),
                            PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                            PushNotification::PARAMETER_SPECIFIC_KEY => $activity->getType(),
                        ]
                    )
                );

                $em->persist($activity);
                $em->flush();
            }
        }

        $em->flush();

        $matchingClient->publishEvent('meetingScheduleUpdated', $user, [
            'id' => $eventSchedule->id->toString(),
        ]);

        return $this->handleResponse(new EventScheduleResponse(
            $eventSchedule,
            true,
            true,
            null,
            false,
            $this->getPredefinedClubParticipantsInfo($eventSchedule)
        ));
    }

    /**
     * @SWG\Get(
     *     description="Get upcoming events",
     *     summary="Get upcoming events",
     *     tags={"Event"},
     *     @SWG\Parameter(type="string", in="query", name="clubId", description="Filter club id"),
     *     @SWG\Response(response="200", description="Success"),
     * )
     * @ListResponse(
     *     entityClass=EventScheduleResponse::class,
     *     paginationByLastValue=true,
     *     pagination=true,
     *     enableOrderBy=false,
     * )
     *
     * @Route("/upcoming", methods={"GET"})
     */
    public function upcoming(
        Request $request,
        MatchingClient $matchingClient,
        RequestApprovePrivateMeetingChangeRepository $approvePrivateMeetingChangeRepository,
        LoggerInterface $logger
    ): JsonResponse {
        $user = $this->getUser();

        $lastValue = $request->query->get('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);
        $clubId = $request->query->get('clubId');

        //@todo remove after hotfix in mobile app with deeplink parser error will be released
        if (mb_strpos($clubId, '_eventId_') !== false) {
            $clubId = explode('_eventId_', $clubId)[0] ?? $clubId;
        }

        if (!$clubId) {
            try {
                $eventSchedulesFromMatching = $matchingClient->findEventScheduleForUser(
                    $user,
                    $request->query->getBoolean('isCalendar'),
                    $limit,
                    $lastValue
                );

                $eventSchedulesFromMatchingIds = array_filter(
                    array_map(
                        fn(array $eventSchedule) => $eventSchedule['id'],
                        $eventSchedulesFromMatching['data'] ?? []
                    ),
                    fn($uuid) => Uuid::isValid($uuid)
                );

                $result = $this->eventScheduleRepository->findUpcomingEventsByIds(
                    $user,
                    $eventSchedulesFromMatchingIds
                );

                $sortedEventSchedules = [];
                foreach ($eventSchedulesFromMatchingIds as $eventScheduleId) {
                    foreach ($result as $data) {
                        /** @var EventSchedule $eventSchedule */
                        $eventSchedule = $data[0];
                        unset($data[0]);

                        if ($eventSchedule->id->toString() == $eventScheduleId) {
                            $sortedEventSchedules[] = array_merge([$eventSchedule], $data);
                        }
                    }
                }


                $result = array_map('array_values', $sortedEventSchedules);
                $lastValue = $eventSchedulesFromMatching['lastValue'] ?? null;
            } catch (Throwable $exception) {
                $logger->error($exception, ['exception' => $exception]);

                list($result, $lastValue) = $this->eventScheduleRepository->findUpcomingEventSchedule(
                    $user,
                    $clubId,
                    $lastValue,
                    $limit
                );
                $result = array_map('array_values', $result);
            }
        } else {
            list($result, $lastValue) = $this->eventScheduleRepository->findUpcomingEventSchedule(
                $user,
                $clubId,
                $lastValue,
                $limit
            );
            $result = array_map('array_values', $result);
        }

        $eventScheduleIds = array_map(fn(array $row) => $row[0]->id->toString(), $result);
        $interests = $this->eventScheduleRepository->findEventScheduleInterests($eventScheduleIds);
        $predefinedClubInfo = $this->clubParticipantRepository
                                   ->fetchEventSchedulesParticipantInformation($eventScheduleIds);
        $requestApproveData = $approvePrivateMeetingChangeRepository->findNeedApproveStatusForEventSchedules(
            $user,
            $eventScheduleIds
        );

        $predefinedRequestApproveData = [];
        /** @var RequestApprovePrivateMeetingChange $requestApprove */
        foreach ($requestApproveData as $requestApprove) {
            $predefinedRequestApproveData[$requestApprove->eventSchedule->id->toString()] = true;
        }

        $response = [];
        foreach ($result as list($eventSchedule, $isAlreadySubscribedToAllParticipants, $isOwned, $isSubscribed)) {
            $item = new EventScheduleResponse(
                $eventSchedule,
                $isAlreadySubscribedToAllParticipants,
                $isOwned,
                $interests[$eventSchedule->id->toString()] ?? [],
                $isSubscribed,
                $predefinedClubInfo[$eventSchedule->id->toString()] ?? [],
            );

            if (isset($predefinedRequestApproveData[$eventSchedule->id->toString()])) {
                $item->needApprove = true;
            }

            $response[] = $item;
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get personal upcoming events",
     *     summary="Get personal upcoming events",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Success"),
     * )
     * @ListResponse(
     *     entityClass=EventScheduleResponse::class,
     *     paginationByLastValue=true,
     *     pagination=true,
     *     enableOrderBy=false,
     * )
     *
     * @Route("/personal", methods={"GET"})
     */
    public function personal(
        Request $request,
        RequestApprovePrivateMeetingChangeRepository $approvePrivateMeetingChangeRepository
    ): JsonResponse {
        $user = $this->getUser();

        $lastValue = $request->query->get('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);

        list($result, $lastValue) = $this->eventScheduleRepository->findUpcomingPersonalEventSchedule(
            $user,
            $lastValue,
            $limit
        );
        $result = array_map('array_values', $result);

        $eventScheduleIds = array_map(fn(array $row) => $row[0]->id->toString(), $result);
        $interests = $this->eventScheduleRepository->findEventScheduleInterests($eventScheduleIds);
        $predefinedClubInfo = $this->clubParticipantRepository
                                   ->fetchEventSchedulesParticipantInformation($eventScheduleIds);
        $requestApproveData = $approvePrivateMeetingChangeRepository->findNeedApproveStatusForEventSchedules(
            $user,
            $eventScheduleIds
        );

        $predefinedRequestApproveData = [];
        /** @var RequestApprovePrivateMeetingChange $requestApprove */
        foreach ($requestApproveData as $requestApprove) {
            $predefinedRequestApproveData[$requestApprove->eventSchedule->id->toString()] = true;
        }

        $response = [];
        foreach ($result as list($eventSchedule, $isAlreadySubscribedToAllParticipants, $isOwned, $isSubscribed)) {
            $item = new EventScheduleResponse(
                $eventSchedule,
                $isAlreadySubscribedToAllParticipants,
                $isOwned,
                $interests[$eventSchedule->id->toString()] ?? [],
                $isSubscribed,
                $predefinedClubInfo[$eventSchedule->id->toString()] ?? [],
            );

            if (isset($predefinedRequestApproveData[$eventSchedule->id->toString()])) {
                $item->needApprove = true;
            }

            $response[] = $item;
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Delete (
     *     description="Remove event schedule",
     *     summary="Remove event schedule",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Success"),
     * )
     * @ViewResponse(errorCodesMap={
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_ERROR_NOT_FOUND, "Not found or access denied"}
     * })
     * @Route("/{id}", methods={"DELETE"})
     */
    public function delete(
        string $id,
        VideoRoomRepository $videoRoomRepository,
        ActivityRepository $activityRepository,
        EventScheduleSubscriptionRepository $eventScheduleSubscriptionRepository,
        MatchingClient $matchingClient
    ): JsonResponse {
        $eventSchedule = $this->eventScheduleRepository->find($id);
        if (!$eventSchedule || !$this->isGranted(EventScheduleVoter::EVENT_SCHEDULE_DELETE_EVENT, $eventSchedule)) {
            return $this->createErrorResponse([ErrorCode::V1_ERROR_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        if ($eventSchedule->videoRoom) {
            $eventSchedule->videoRoom->eventSchedule = null;
            $videoRoomRepository->save($eventSchedule->videoRoom);
        }

        $eventScheduleSubscriptionRepository->deleteSubscriptionForEvent($eventSchedule);
        $activityRepository->deleteActivitiesWithEventScheduleId($eventSchedule->id->toString());
        $this->eventScheduleRepository->remove($eventSchedule);

        $matchingClient->publishEvent('eventScheduleRemoved', $eventSchedule->owner, ['id' => $id]);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get(
     *     description="Get event schedule",
     *     summary="Get event schedule",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Success"),
     * )
     * @ViewResponse(
     *     entityClass=EventScheduleWithTokenResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_ERROR_NOT_FOUND, "Not found or access denied"}
     *     }
     * )
     * @Route("/{id}", methods={"GET"})
     */
    public function item(
        string $id,
        InfuraClient $infuraClient,
        RequestApprovePrivateMeetingChangeRepository $approvePrivateMeetingChangeRepository,
        LoggerInterface $logger
    ): JsonResponse {
        if (!Uuid::isValid($id)) {
            return $this->createErrorResponse(ErrorCode::V1_BAD_REQUEST, Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        $result = $this->eventScheduleRepository->findEventSchedule($currentUser, $id);
        if (!$result) {
            return $this->createErrorResponse([ErrorCode::V1_ERROR_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }
        $result = array_values($result);

        /**
         * @var EventSchedule $eventSchedule
         * @var bool $isAlreadySubscribed
         * @var bool $isOwned
         */
        list($eventSchedule, $isAlreadySubscribed, $isOwned, $isSubscribed) = $result;

        $response = new EventScheduleWithTokenResponse(
            $eventSchedule,
            $isAlreadySubscribed,
            $isOwned,
            null,
            $isSubscribed,
            $this->getPredefinedClubParticipantsInfo($eventSchedule)
        );

        if ($currentUser) {
            $response->needApprove = $approvePrivateMeetingChangeRepository->findOneBy([
                'eventSchedule' => $eventSchedule,
                'user' => $currentUser,
                'reviewed' => false,
            ]) !== null;
        }

        if (!$eventSchedule->forOwnerTokens->isEmpty()) {
            $response->isOwnerToken = false;
            $response->tokenReason = null;
            if ($currentUser) {
                $response->isOwnerToken = true;
                if (!$currentUser->wallet) {
                    $response->tokenReason = EventScheduleWithTokenResponse::WALLET_NOT_REGISTERED;
                    $response->isOwnerToken = false;
                } else {
                    foreach ($eventSchedule->forOwnerTokens as $eventToken) {
                        $token = $eventToken->token;

                        $response->tokenLandingUrlInformation = $token->landingUrl;
                        $response->tokens[] = new SlimTokenResponse($token);

                        try {
                            $balanceOf = $infuraClient->getSmartContract($token)->getBalance(
                                $token,
                                $currentUser->wallet
                            );

                            if ($balanceOf < $token->minAmount) {
                                $response->tokenReason = EventScheduleWithTokenResponse::TOKEN_NOT_FOUND;
                                $response->isOwnerToken = false;
                                break;
                            }
                        } catch (Throwable $exception) {
                            $logger->error($exception, ['exception' => $exception, 'token' => $token->tokenId]);

                            $response->tokenReason = EventScheduleWithTokenResponse::WALLET_ERROR;
                            $response->isOwnerToken = false;
                            break;
                        }
                    }
                }
            } else {
                $response->tokenReason = EventScheduleWithTokenResponse::WALLET_NOT_REGISTERED;
                foreach ($eventSchedule->forOwnerTokens as $eventToken) {
                    $token = $eventToken->token;

                    $response->tokenLandingUrlInformation = $token->landingUrl;
                    $response->tokens[] = new SlimTokenResponse($token);
                }
            }
        }

        return $this->handleResponse($response);
    }

    private function getPredefinedClubParticipantsInfo(EventSchedule $eventSchedule): array
    {
        $predefinedClubInfo = [];

        if ($eventSchedule->club) {
            $predefinedClubInfo = $this->clubParticipantRepository
                ->fetchEventScheduleParticipantInformation($eventSchedule);
        }

        return $predefinedClubInfo;
    }
}
