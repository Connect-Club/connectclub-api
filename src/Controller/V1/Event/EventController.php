<?php

namespace App\Controller\V1\Event;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Club\ClubSlimResponse;
use App\DTO\V1\Event\EventScheduleParticipantResponse;
use App\DTO\V1\Event\OnlineEventItem;
use App\DTO\V1\Event\OnlineEventUserInfo;
use App\DTO\V1\Event\StatisticsRequest;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\VideoRoom\VideoRoomCreateResponse;
use App\Entity\Activity\InvitePrivateVideoRoomActivity;
use App\Entity\Club\ClubParticipant;
use App\Entity\Community\CommunityParticipant;
use App\Entity\Event\EventDraft;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Interest\Interest;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomEvent;
use App\Repository\Activity\InvitePrivateVideoRoomActivityRepository;
use App\Repository\Community\CommunityRepository;
use App\Repository\Event\EventScheduleRepository;
use App\Repository\Follow\FollowRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoMeetingParticipantRepository;
use App\Repository\VideoChat\VideoRoomEventRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\ActivityManager;
use App\Service\EventManager;
use App\Service\JitsiEndpointManager;
use App\Service\MatchingClient;
use App\Service\Notification\Message\ReactNativeVideoRoomNotification;
use App\Service\Notification\NotificationManager;
use App\Service\Notification\Push\ReactNativePushNotification;
use App\Service\Notification\Push\PushNotification;
use App\Service\VideoRoomManager;
use App\Service\VideoRoomNotifier;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\Common\Collections\ArrayCollection;
use Nelmio\ApiDocBundle\Annotation\Model;
use Redis;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/event")
 */
class EventController extends BaseController
{
    private VideoRoomRepository $videoRoomRepository;

    public function __construct(VideoRoomRepository $videoRoomRepository)
    {
        $this->videoRoomRepository = $videoRoomRepository;
    }

    /**
     * @SWG\Post(
     *     description="Close event meeting",
     *     summary="Close event meeting",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Successfully close meeting"),
     * )
     * @ViewResponse(errorCodesMap={
     *     {Response::HTTP_BAD_REQUEST, ErrorCode::V1_EVENT_NO_ACTIVE_MEETING, "No active meeting"},
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, "Not found video room"},
     * })
     * @Route("/{name}/close", methods={"POST"})
     */
    public function close(string $name, JitsiEndpointManager $jitsiEndpointManager): JsonResponse
    {
        $user = $this->getUser();

        $videoRoom = $this->videoRoomRepository->findOneByName($name);

        $isModerator = false;
        if ($videoRoom) {
            $participant = $videoRoom->community->getParticipant($user);
            $isModerator = $participant && in_array($participant->role, [
                CommunityParticipant::ROLE_MODERATOR,
                CommunityParticipant::ROLE_ADMIN,
            ]);
        }

        if (!$videoRoom || !$isModerator) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $activeMeeting = $videoRoom->getActiveMeeting();
        if (!$activeMeeting) {
            return $this->createErrorResponse([ErrorCode::V1_EVENT_NO_ACTIVE_MEETING], Response::HTTP_BAD_REQUEST);
        }

        if ($videoRoom->alwaysReopen || $videoRoom->alwaysOnline) {
            return $this->handleResponse([]);
        }

        $onlineParticipants = $activeMeeting->participants
                                            ->filter(fn(VideoMeetingParticipant $p) => $p->endTime === null);

        $videoRoom->doneAt = time();
        $this->videoRoomRepository->save($videoRoom);

        foreach ($onlineParticipants as $participant) {
            $jitsiEndpointManager->disconnectUserFromRoom($participant->participant, $videoRoom);
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get (
     *     description="List online events",
     *     summary="List online events",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Successfully"),
     * )
     * @ListResponse(entityClass=OnlineEventItem::class)
     * @Route("/online", methods={"GET"})
     */
    public function online(
        Request $request,
        VideoMeetingParticipantRepository $videoMeetingParticipantRepository,
        EventScheduleRepository $eventScheduleRepository,
        Redis $redis
    ): JsonResponse {
        $user = $this->getUser();

        $lastValue = $request->query->getInt('lastValue', 0);
        $limit = $request->query->getInt('limit', 20);

        $ignore = [];
//        if ($lastValue) {
//            $ignore = $redis->get('ignore_video_rooms_'.$user->id.'_'.$lastValue) ?? null;
//            if ($ignore) {
//                $ignore = json_decode($ignore, true) ?? [];
//                $redis->del('ignore_video_rooms_'.$user->id.'_'.$lastValue);
//            }
//        }

        list($items, $lastValue) = $this->videoRoomRepository->findOnlineVideoRoom($user, $lastValue, $limit, $ignore);
        $items = array_map('array_values', $items);

        $ids = [];
        /** @var VideoRoom $videoRoom */
        foreach ($items as list($videoRoom,)) {
            $ids[] = $videoRoom->id;
        }
        $redis->set('ignore_video_rooms_'.$user->id.'_'.$lastValue, json_encode($ids));
        $redis->expire('ignore_video_rooms_'.$user->id.'_'.$lastValue, 60 * 5);

        if (!$items) {
            return $this->handleResponse(new PaginatedResponse([]));
        }

        $videoRoomNames = array_map(fn(array $item) => $item[0]->community->name, $items);

        $eventScheduleIds = [];
        foreach ($items as list($videoRoom,)) {
            if ($videoRoom->eventSchedule) {
                $eventScheduleIds[] = $videoRoom->eventSchedule->id->toString();
            }
        }
        $eventScheduleInterests = $eventScheduleRepository->findEventScheduleInterests($eventScheduleIds);

        /** @var VideoMeetingParticipant[] $onlineParticipants */
        $onlineParticipants = [];
        foreach ($videoMeetingParticipantRepository->findOnlineParticipantsInVideoRooms($videoRoomNames) as $item) {
            $onlineParticipants[$item->videoMeeting->videoRoom->community->name] ??= [];
            $onlineParticipants[$item->videoMeeting->videoRoom->community->name][] = $item;
        }

        /** @var OnlineEventUserInfo[] $preparedParticipantsInfo */
        $preparedParticipantsInfo = [];
        /** @var User[] $specialGuestsIdsByVideoRoomName */
        $specialGuestsIdsByVideoRoomName = [];

        /**
         * @var VideoRoom $room
         * @var int $countOnlineParticipants.
         */
        foreach ($items as list($room, $countOnlineParticipants)) {
            $roomName = $room->community->name;

            $participantsCollection = new ArrayCollection($onlineParticipants[$room->community->name] ?? []);
            $speakers = $speakersInfo[$roomName] = $participantsCollection
                ->filter(fn(VideoMeetingParticipant $p) => $p->endTime === null)
                ->filter(fn(VideoMeetingParticipant $p) => $p->endpointAllowIncomingMedia === true)
                ->map(fn(VideoMeetingParticipant $p) => $p->participant)
                ->getValues();

            $specialGuestsIds = [];
            if ($room->eventSchedule) {
                $specialGuestsIds = $room->eventSchedule->participants
                     ->filter(fn(EventScheduleParticipant $p) => $p->isSpecialGuest)
                     ->map(fn(EventScheduleParticipant $p) => $p->user->id)
                     ->getValues();

                $specialGuestsIds = $specialGuestsIdsByVideoRoomName[$roomName]
                                  = array_combine($specialGuestsIds, $specialGuestsIds);
            }

            if (count($speakers) >= 4) {
                $participants = array_map(
                    fn(User $s) => new OnlineEventUserInfo(true, $s, isset($specialGuestsIds[$s->id])),
                    array_slice($speakers, 0, 4)
                );
            } else {
                $participants = array_map(
                    fn(User $s) => new OnlineEventUserInfo(true, $s, isset($specialGuestsIds[$s->id])),
                    $speakers
                );
                $speakersIds = array_map(fn(User $speaker) => $speaker->id, $speakers);

                if (isset($onlineParticipants[$roomName])) {
                    //Online users, but except speakers
                    $additionalParticipants = array_slice(
                        array_filter(
                            $onlineParticipants[$roomName],
                            fn(VideoMeetingParticipant $p) => !in_array($p->participant->id, $speakersIds)
                        ),
                        0,
                        4 - count($speakers)
                    );

                    $participants = array_merge(
                        $participants,
                        array_map(
                            fn(VideoMeetingParticipant $p) => new OnlineEventUserInfo(
                                false,
                                $p->participant,
                                isset($specialGuestsIds[$p->participant->id])
                            ),
                            $additionalParticipants
                        )
                    );
                }
            }

            usort($participants, function (OnlineEventUserInfo $a, OnlineEventUserInfo $b) use ($room) {
                $calc = function (OnlineEventUserInfo $u) use ($room): int {
                    $balls = 0;

                    if ($u->isSpecialGuest) {
                        $balls += 5;
                    } elseif ($u->id == $room->community->owner->id) {
                        $balls += 4;
                    } elseif ($u->isSpeaker) {
                        $balls += 3;
                    }

                    return $balls;
                };

                $a = $calc($a);
                $b = $calc($b);

                if ($a == $b) {
                    return 0;
                }

                return ($a < $b) ? 1 : -1;
            });

            $preparedParticipantsInfo[$room->community->name] = array_values($participants);
        }

        $response = [];
        /**
         * @var VideoRoom $room
         * @var int $countOnlineParticipants
         */
        foreach ($items as list($room, $countOnlineParticipants)) {
            $countSpeakers = count($speakersInfo[$room->community->name] ?? []);

            $onlineUsers = $onlineParticipants[$room->community->name] ?? [];
            $onlineSpeakers = $onlineListeners = [];

            /** @var VideoMeetingParticipant $onlineUser */
            foreach ($onlineUsers as $onlineUser) {
                if ($onlineUser->endpointAllowIncomingMedia) {
                    $onlineSpeakers[$onlineUser->participant->id] = $onlineUser->participant;
                } else {
                    $onlineListeners[$onlineUser->participant->id] = $onlineUser->participant;
                }
            }

            $onlineSpeakers = array_slice($onlineSpeakers, 0, 3);
            $onlineListeners = array_slice($onlineListeners, 0, 4);

            $isCoHost = $room->eventSchedule && !$room->eventSchedule->participants->filter(
                fn(EventScheduleParticipant $participant) => $participant->user->equals($user)
            )->isEmpty();

            if ($room->eventSchedule) {
                $interests = $eventScheduleInterests[$room->eventSchedule->id->toString()] ?? [];
            } else {
                $interests = $room->community->owner->interests->toArray();
            }

            $totalInterests = [];
            foreach ($interests as $interest) {
                $totalInterests[$interest->id] = $interest;
            }
            $totalInterests = array_values($totalInterests);

            $dto = new OnlineEventItem(
                $room->community->description,
                $preparedParticipantsInfo[$room->community->name],
                $countOnlineParticipants - $countSpeakers,
                $countSpeakers,
                $room,
                $isCoHost,
                $totalInterests
            );

            if ($room->eventSchedule) {
                $dto->eventScheduleId = $room->eventSchedule->id->toString();
            }

            if ($room->eventSchedule && $room->eventSchedule->club) {
                $dto->club = new ClubSlimResponse($room->eventSchedule->club);
            }

            $roomName = $room->community->name;

            $dto->speakers = array_map(
                fn(User $user) => new OnlineEventUserInfo(
                    true,
                    $user,
                    isset($specialGuestsIdsByVideoRoomName[$roomName][$user->id])
                ),
                $onlineSpeakers
            );
            $dto->listeners = array_map(
                fn(User $user) => new OnlineEventUserInfo(
                    false,
                    $user,
                    isset($specialGuestsIdsByVideoRoomName[$roomName][$user->id])
                ),
                $onlineListeners
            );

            $response[] = $dto;
        }

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Post(
     *     description="Create private event with user",
     *     summary="Create private event with user",
     *     tags={"Event"},
     *     @SWG\Response(response=Response::HTTP_OK, description="Successfully"),
     * )
     * @ViewResponse(
     *     entityClass=VideoRoomCreateResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User is not friend or not found"},
     *     }
     * )
     * @Route("/private/{userId}", methods={"POST"}, requirements={"userId": "\d+"})
     */
    public function privateEvent(
        int $userId,
        InvitePrivateVideoRoomActivityRepository $invitePrivateVideoRoomActivityRepository,
        NotificationManager $notificationManager,
        ActivityManager $activityManager,
        VideoRoomManager $videoRoomManager,
        FollowRepository $followRepository,
        CommunityRepository $communityRepository
    ): JsonResponse {
        $user = $this->getUser();

        $friend = $followRepository->findFriendById($user, $userId);
        if (!$friend) {
            return $this->createErrorResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $videoRoom = $videoRoomManager->createVideoRoomByType(EventDraft::TYPE_PRIVATE, $user);
        $videoRoom->addInvitedUser($user);
        $videoRoom->addInvitedUser($friend);
        $videoRoom->isPrivate = true;

        $community = $videoRoom->community;
        $community->participants->add(new CommunityParticipant($user, $community, CommunityParticipant::ROLE_ADMIN));

        $communityRepository->save($community);

        $activity = new InvitePrivateVideoRoomActivity($videoRoom, $friend, $user);
        $invitePrivateVideoRoomActivityRepository->save($activity);

        $notificationManager->sendNotifications($friend, new ReactNativeVideoRoomNotification(
            $videoRoom,
            $activityManager->getActivityTitle($activity),
            $activityManager->getActivityDescription($activity),
            [
                PushNotification::PARAMETER_INITIATOR_ID => $user->id,
                PushNotification::PARAMETER_SPECIFIC_KEY => 'create-private-room',
                PushNotification::PARAMETER_IMAGE => $user->getAvatarSrc(300, 300)
            ],
        ));

        return $this->handleResponse(new OnlineEventItem(null, [], 0, 0, $videoRoom));
    }

    /**
     * @SWG\Post(
     *     description="Promote user in event",
     *     summary="Promote user in event",
     *     tags={"Event", "Internal"},
     *     @SWG\Response(response="200", description="Success")
     * )
     * @ViewResponse(errorCodesMap={
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, "Event not found"},
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found"},
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found in event"},
     * })
     * @Route("/{eventId}/{userId}/promote", requirements={"userId": "\d+"}, methods={"POST"})
     */
    public function promote(
        string $eventId,
        int $userId,
        UserRepository $userRepository,
        VideoRoomRepository $videoRoomRepository
    ): JsonResponse {
        $videoRoom = $videoRoomRepository->findOneByName($eventId);
        if (!$videoRoom) {
            return $this->handleResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->handleResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $communityParticipant = $videoRoom->community->getParticipant($user);
        if (!$communityParticipant) {
            return $this->handleResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $communityParticipant->role = CommunityParticipant::ROLE_MODERATOR;
        $userRepository->save($communityParticipant);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Demote user in event",
     *     summary="Demote user in event",
     *     tags={"Event", "Internal"},
     *     @SWG\Response(response="200", description="Success")
     * )
     * @ViewResponse(errorCodesMap={
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, "Event not found"},
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found"},
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found in event"},
     * })
     * @Route("/{eventId}/{userId}/demote", requirements={"userId": "\d+"}, methods={"POST"})
     */
    public function demote(
        string $eventId,
        int $userId,
        UserRepository $userRepository,
        VideoRoomRepository $videoRoomRepository
    ): JsonResponse {
        $videoRoom = $videoRoomRepository->findOneByName($eventId);
        if (!$videoRoom) {
            return $this->handleResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->handleResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $communityParticipant = $videoRoom->community->getParticipant($user);
        if (!$communityParticipant) {
            return $this->handleResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $communityParticipant->role = CommunityParticipant::ROLE_MEMBER;
        $userRepository->save($communityParticipant);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Make event as public",
     *     summary="Make event as public",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="Success")
     * )
     * @ViewResponse(errorCodesMap={
     *     {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, "Event not found"},
     * })
     * @Route("/{eventId}/public", methods={"POST"})
     */
    public function makePublic(
        string $eventId,
        EventManager $eventManager,
        VideoRoomNotifier $roomNotifier
    ): JsonResponse {
        $videoRoom = $this->videoRoomRepository->findOneByName($eventId);
        if (!$videoRoom) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $participant = $videoRoom->community->getParticipant($this->getUser());
        $isModerator = $participant && in_array($participant->role, [
            CommunityParticipant::ROLE_MODERATOR,
            CommunityParticipant::ROLE_ADMIN,
        ]);

        if (!$isModerator) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $videoRoom->isPrivate = false;
        $this->videoRoomRepository->save($videoRoom);

        if ($videoRoom->eventSchedule) {
            $eventManager->sendNotifications($videoRoom->eventSchedule);
        }

        $roomNotifier->notifyStarted($videoRoom);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Load event for statistic",
     *     summary="Load event for statistic",
     *     tags={"Event"},
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         @SWG\Schema(ref=@Model(type=StatisticsRequest::class))
     *     ),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(
     *     entityClass=StatisticsRequest::class,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_USER_NOT_FOUND, "User not found"},
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, "Video room not found"},
     *     }
     * )
     * @Route("/{name}/statistic", methods={"POST"})
     */
    public function statistic(
        string $name,
        Request $request,
        UserRepository $userRepository,
        VideoRoomEventRepository $videoRoomEventRepository,
        MatchingClient $matchingClient
    ): JsonResponse {
        /** @var StatisticsRequest $loadStatisticRequest */
        $loadStatisticRequest = $this->getEntityFromRequestTo($request, StatisticsRequest::class);

        $videoRoom = $this->videoRoomRepository->findOneByName($name);
        if (!$videoRoom) {
            return $this->handleResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $user = $userRepository->find((int) $loadStatisticRequest->userId);
        if (!$user) {
            return $this->handleResponse(ErrorCode::V1_USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $event = $loadStatisticRequest->event;

        $videoRoomEventRepository->save(new VideoRoomEvent($videoRoom, $user, $event));

        $interests = $videoRoom->eventSchedule ?
                     $videoRoom
                           ->eventSchedule
                           ->interests->map(fn(EventScheduleInterest $i) => $i->interest)
                           ->toArray() :
                     $videoRoom->community->owner->interests->toArray();

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Cancels an invite",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Video room invite not found"),
     *     @SWG\Response(response="403", description="Access denied")
     * )
     * @Route("/invite/{inviteId}/cancel", methods={"POST"})
     */
    public function cancelInvite(
        string $inviteId,
        NotificationManager $notificationManager
    ): JsonResponse {
        $videoRoom = $this->videoRoomRepository->findOneByName($inviteId);
        if (!$videoRoom) {
            return $this->createErrorResponse(
                ErrorCode::V1_PRIVATE_VIDEO_ROOM_INVITE_NOT_FOUND,
                Response::HTTP_NOT_FOUND
            );
        }

        $currentUser = $this->getUser();

        if (!$videoRoom->isInvitedUser($currentUser)) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        foreach ($videoRoom->invitedUsers as $invitedUser) {
            if ($invitedUser->equals($currentUser)) {
                continue;
            }

            $notificationManager->sendNotifications(
                $invitedUser,
                new ReactNativePushNotification(
                    'invite-cancelled',
                    null,
                    'notifications.invite-cancelled',
                    [
                        'inviteId' => $videoRoom->community->name,
                        PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                        PushNotification::PARAMETER_SPECIFIC_KEY => 'invite-cancelled',
                    ],
                )
            );
        }

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Accepts an invite",
     *     tags={"Event"},
     *     @SWG\Response(response="200", description="OK"),
     *     @SWG\Response(response="404", description="Video room invite not found"),
     *     @SWG\Response(response="403", description="Access denied")
     * )
     * @Route("/invite/{inviteId}/accept", methods={"POST"})
     */
    public function acceptInvite(
        string $inviteId,
        NotificationManager $notificationManager
    ): JsonResponse {
        $videoRoom = $this->videoRoomRepository->findOneByName($inviteId);
        if (!$videoRoom) {
            return $this->createErrorResponse(
                ErrorCode::V1_PRIVATE_VIDEO_ROOM_INVITE_NOT_FOUND,
                Response::HTTP_NOT_FOUND
            );
        }

        $currentUser = $this->getUser();

        if (!$videoRoom->isInvitedUser($currentUser)) {
            return $this->createErrorResponse(
                ErrorCode::V1_ACCESS_DENIED,
                Response::HTTP_FORBIDDEN
            );
        }

        if ($currentUser->equals($videoRoom->community->owner)) {
            return $this->createErrorResponse(
                ErrorCode::V1_ACCESS_DENIED,
                Response::HTTP_FORBIDDEN
            );
        }

        foreach ($videoRoom->invitedUsers as $invitedUser) {
            if ($invitedUser->equals($currentUser)) {
                continue;
            }

            $notificationManager->sendNotifications(
                $invitedUser,
                new ReactNativePushNotification(
                    'invite-accepted',
                    null,
                    null,
                    [
                        'inviteId' => $videoRoom->community->name,
                        'videoRoomId' => $videoRoom->id,
                        'videoRoomPassword' => $videoRoom->community->password,
                        PushNotification::PARAMETER_INITIATOR_ID => $currentUser->id,
                        PushNotification::PARAMETER_SPECIFIC_KEY => 'invite-accepted',
                    ],
                )
            );
        }

        return $this->handleResponse([]);
    }
}
