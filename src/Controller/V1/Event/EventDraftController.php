<?php

namespace App\Controller\V1\Event;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Event\CreateCallRequest;
use App\DTO\V1\Event\CreateCallResponse;
use App\DTO\V1\Event\CreateEventFromDraft;
use App\DTO\V1\Event\OnlineEventItem;
use App\DTO\V1\VideoRoom\VideoRoomCreateResponse;
use App\DTO\V2\Event\EventDraftResponse;
use App\Entity\Activity\InvitePrivateVideoRoomActivity;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Event\EventDraft;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\User;
use App\Repository\Activity\InvitePrivateVideoRoomActivityRepository;
use App\Repository\Event\EventDraftRepository;
use App\Repository\Event\EventScheduleRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\Interest\InterestRepository;
use App\Repository\User\LanguageRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Security\Voter\Event\EventScheduleVoter;
use App\Service\ActivityManager;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\PushNotification;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\VideoRoomManager;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @Route("/event-draft") */
class EventDraftController extends BaseController
{
    private EventDraftRepository $eventDraftRepository;

    public function __construct(EventDraftRepository $eventDraftRepository)
    {
        $this->eventDraftRepository = $eventDraftRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get all drafts",
     *     summary="Get all drafts",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Get all drafts")
     * )
     * @ViewResponse(entityClass=EventDraftResponse::class)
     * @Route("", methods={"GET"})
     */
    public function drafts(): JsonResponse
    {
        $response = array_map(
            fn (EventDraft $draft) => new EventDraftResponse($draft),
            $this->eventDraftRepository->findAll()
        );

        return $this->handleResponse($response);
    }

    /**
     * @SWG\Post(
     *     description="Create video room by draft",
     *     summary="Create video room by draft",
     *     tags={"Event"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateEventFromDraft::class))),
     *     @SWG\Response(response=Response::HTTP_CREATED, description="Success create new video room")
     * )
     * @ViewResponse(
     *     entityClass=VideoRoomCreateResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_DRAFT_NOT_FOUND, "Draft not found"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "Friend not found with userId"},
     *         {Response::HTTP_LOCKED, ErrorCode::V1_ERROR_ACTION_LOCK, "Current event schedule locked for add room"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_EVENT_SCHEDULE_NOT_FOUND, "Event schedule not found"}
     *     }
     * )
     *
     * @Route("/{draftId}/event", methods={"POST"})
     */
    public function create(
        VideoRoomManager $videoRoomManager,
        EventScheduleRepository $eventScheduleRepository,
        VideoRoomRepository $videoRoomRepository,
        Request $request,
        LockFactory $factory,
        FollowRepository $followRepository,
        InvitePrivateVideoRoomActivityRepository $invitePrivateVideoRoomActivityRepository,
        NotificationManager $notificationManager,
        InterestRepository $interestRepository,
        LanguageRepository $languageRepository,
        ActivityManager $activityManager,
        string $draftId
    ): JsonResponse {
        $user = $this->getUser();

        /** @var CreateEventFromDraft $createEventFromDraft */
        $createEventFromDraft = $this->getEntityFromRequestTo($request, CreateEventFromDraft::class);
        $eventSchedule = null;

        $friend = null;
        if ($createEventFromDraft->userId) {
            $friend = $followRepository->findFriendById($user, (int) $createEventFromDraft->userId);
            if (!$friend) {
                return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }
        }

        $lock = null;
        if ($eventScheduleId = $createEventFromDraft->eventScheduleId) {
            $eventSchedule = $eventScheduleRepository->find($eventScheduleId);
            if (!$eventSchedule || !$this->isGranted(EventScheduleVoter::EVENT_SCHEDULE_START_EVENT, $eventSchedule)) {
                return $this->createErrorResponse(ErrorCode::V1_EVENT_SCHEDULE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }
            $lock = $factory->createLock('create_video_room_for_event_schedule_'.$eventScheduleId, 1000);

            if (!$lock->acquire()) {
                return $this->createErrorResponse(ErrorCode::V1_ERROR_ACTION_LOCK, Response::HTTP_LOCKED);
            }
        }

        if ($eventSchedule && $eventSchedule->videoRoom) {
            return $this->handleResponse(
                new OnlineEventItem(
                    $eventSchedule->videoRoom->community->description,
                    [],
                    0,
                    0,
                    $eventSchedule->videoRoom,
                    true
                ),
                Response::HTTP_CREATED
            );
        }

        $draft = $this->eventDraftRepository->find($draftId);
        if (!$draft) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_DRAFT_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $description = $eventSchedule ? $eventSchedule->name : $createEventFromDraft->title;
        $videoRoom = $videoRoomManager->createVideoRoomFromDraft($draft, $user, $description);
        $videoRoom->eventSchedule = $eventSchedule;

        if ($languageId = $createEventFromDraft->language) {
            $videoRoom->language = $languageRepository->find($languageId);
        }

        if ($createEventFromDraft->isPrivate !== null) {
            $videoRoom->isPrivate = (bool) $createEventFromDraft->isPrivate;
            $videoRoom->addInvitedUser($user);

            if ($friend) {
                $videoRoom->addInvitedUser($friend);
            }
        }

        $community = $videoRoom->community;

        if ($eventSchedule) {
            /** @var EventScheduleParticipant $participant */
            foreach ($eventSchedule->participants as $participant) {
                $role = $participant->isSpecialGuest ?
                        CommunityParticipant::ROLE_SPECIAL_GUESTS :
                        CommunityParticipant::ROLE_ADMIN;

                $community->participants->add(
                    new CommunityParticipant($participant->user, $community, $role)
                );

                if ($createEventFromDraft->isPrivate) {
                    $videoRoom->addInvitedUser($participant->user);
                }
            }
        }

        $videoRoomRepository->save($community);

        if ($friend) {
            $activity = new InvitePrivateVideoRoomActivity($videoRoom, $friend, $user);
            $invitePrivateVideoRoomActivityRepository->save($activity);

            $notificationManager->sendNotifications($friend, new ReactNativeVideoRoomNotification(
                $videoRoom,
                $activityManager->getActivityTitle($activity),
                $activityManager->getActivityDescription($activity),
                [
                    PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                    PushNotification::PARAMETER_SPECIFIC_KEY => 'invite-private-video-room',
                    PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(300, 300)
                ],
            ));
        }

        if ($lock) {
            $lock->release();
        }

        return $this->handleResponse(new OnlineEventItem($description, [], 0, 0, $videoRoom), Response::HTTP_CREATED);
    }

    /**
     * @SWG\Post(
     *     description="Create a call",
     *     tags={"Event"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateCallRequest::class))),
     *     @SWG\Response(response=Response::HTTP_CREATED, description="Success create new video room")
     * )
     * @ViewResponse(
     *     entityClass=CreateCallResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_DRAFT_NOT_FOUND, "Draft not found"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "Friend not found with userId"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_LANGUAGE_NOT_FOUND, "Language not found"}
     *     }
     * )
     * @Route("/{draftId}/call", methods={"POST"})
     */
    public function createCall(
        VideoRoomManager $videoRoomManager,
        FollowRepository $followRepository,
        NotificationManager $notificationManager,
        ActivityManager $activityManager,
        LanguageRepository $languageRepository,
        InterestRepository $interestRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        Request $request,
        string $draftId
    ): JsonResponse {
        $currentUser = $this->getUser();

        /** @var CreateCallRequest $createCallRequest */
        $createCallRequest = $this->getEntityFromRequestTo($request, CreateCallRequest::class);

        $friend = $followRepository->findFriendById($currentUser, (int) $createCallRequest->userId);
        if (!$friend) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $draft = $this->eventDraftRepository->find($draftId);
        if (!$draft) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_DRAFT_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $language = $languageRepository->find($createCallRequest->language);
        if (!$language) {
            return $this->createErrorResponse(ErrorCode::V1_LANGUAGE_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $videoRoom = $videoRoomManager->createVideoRoomFromDraft($draft, $currentUser, $createCallRequest->title);
        $videoRoom->isPrivate = true;
        $videoRoom->addInvitedUser($currentUser);
        $videoRoom->addInvitedUser($friend);
        $videoRoom->language = $language;

        $entityManager->persist($videoRoom);

        $activity = new InvitePrivateVideoRoomActivity($videoRoom, $friend, $currentUser);
        $entityManager->persist($activity);

        $entityManager->flush();

        $notificationManager->sendNotifications($friend, new ReactNativePushNotification(
            'invite-private',
            null,
            $activityManager->getActivityDescription($activity),
            [
                'videoRoomId' => $videoRoom->id,
                'videoRoomPassword' => $videoRoom->community->password,
                'inviteId' => $videoRoom->community->name,
                PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                PushNotification::PARAMETER_SPECIFIC_KEY => 'invite-private',
            ],
        ));

        return $this->handleResponse(new CreateCallResponse(
            $videoRoom,
            $translator->trans('notifications.invite-successfully-created')
        ), Response::HTTP_CREATED);
    }
}
