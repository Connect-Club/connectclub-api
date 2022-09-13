<?php

namespace App\Controller\V2;

use Anboo\ApiBundle\Swagger\ApiResponse;
use App\ConnectClub;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\PaginatedResponseWithCount;
use App\DTO\V1\VideoRoom\VideoRoomResponse;
use App\DTO\V1\VideoRoom\VideoRoomTokenRequest;
use App\DTO\V1\VideoRoom\VideoRoomTokenRequestWithPublic;
use App\DTO\V2\VideoRoom\VideoRoomTokenResponse;
use App\DTO\V1\VideoRoom\VideoRoomPublicResponse;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Entity\VideoChat\VideoRoom;
use App\Event\VideoRoomEvent;
use App\Repository\Event\EventScheduleParticipantRepository;
use App\Repository\SettingsRepository;
use App\Repository\Subscription\PaidSubscriptionRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Repository\VideoChat\VideoRoomBanRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Service\EventLogManager;
use App\Service\InfuraClient;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ethereum\DataType\EthD;
use Lcobucci\JWT\Builder;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Key;
use Throwable;

/**
 * Class RoomController.
 *
 * @Route("/video-room")
 */
class VideoRoomController extends BaseController
{
    /**
     * @SWG\Post(
     *     produces={"application/json"},
     *     description="Create token for joining video room chat",
     *     summary="Create token for joining video room chat",
     *     @SWG\Parameter(name="name", in="path", type="string", description="Room name"),
     *     @SWG\Parameter(schema=@SWG\Schema(
     *         ref=@Model(type=VideoRoomTokenRequest::class)),
     *         in="body", name="body"
     *     ),
     *     @SWG\Response(response="200", description="Success create token"),
     *     @SWG\Response(response="403", description="Password incorrect or user banned"),
     *     @SWG\Response(response="404", description="Room not found"),
     *     @SWG\Response(response="409", description="Video room has active meeting in another video server"),
     *     @SWG\Response(response="412", description="Max count participants reached error"),
     *     tags={"Video Room"}
     * )
     * @Nelmio\Security(name="oauth2BearerToken")
     *
     * @ViewResponse(entityClass=VideoRoomTokenResponse::class, groups={
     *     "v1.room.default",
     *     "v1.upload.default_photo",
     *     "default"
     * })
     * @Route("/token/{name}", methods={"POST"})
     */
    public function token(
        Request                            $request,
        string                             $name,
        VideoRoomRepository                $videoRoomRepository,
        VideoRoomBanRepository             $videoRoomBanRepository,
        VideoMeetingRepository             $videoMeetingRepository,
        PaidSubscriptionRepository         $paidSubscriptionRepository,
        EventLogManager                    $eventLogManager,
        EntityManagerInterface             $entityManager,
        InfuraClient                       $infuraClient,
        EventScheduleParticipantRepository $participantRepository,
        LoggerInterface                    $logger,
        SettingsRepository                 $settingsRepository
    ): JsonResponse {
        if (!$room = $videoRoomRepository->findOneByName($name)) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $settings = $settingsRepository->findActualSettings();

        /** @var VideoRoomTokenRequest $videoRoomTokenRequest */
        $videoRoomTokenRequest = $this->getEntityFromRequestTo($request, VideoRoomTokenRequest::class);

        if ($room->community->password != $videoRoomTokenRequest->password) {
            return $this->createErrorResponse(
                [ErrorCode::V1_VIDEO_ROOM_INCORRECT_PASSWORD],
                Response::HTTP_FORBIDDEN
            );
        }

        /** @var VideoRoomTokenRequestWithPublic $vrTokenRequestWithPublic */
        $vrTokenRequestWithPublic = $this->getEntityFromRequestTo($request, VideoRoomTokenRequestWithPublic::class);
        if (!empty($vrTokenRequestWithPublic->publicRoomData)) {
            return $this->handleResponse(new VideoRoomPublicResponse($room->config->withSpeakers));
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if ($room->doneAt !== null &&
            !$room->alwaysReopen &&
            !$room->alwaysOnline &&
            !($currentUser && $room->community->owner->equals($currentUser))
        ) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_ACTION_LOCK, Response::HTTP_LOCKED);
        }

        if ($currentUser && $room->mustPayForAccess($currentUser)) {
            $paidSubscription = $paidSubscriptionRepository->findActive($room->subscription, $currentUser);

            if (!$paidSubscription) {
                return $this->createErrorResponse(
                    [ErrorCode::V1_VIDEO_ROOM_PAYMENT_REQUIRED],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        if ($currentUser && $videoRoomBanRepository->findBan($currentUser, $room)) {
            return $this->createErrorResponse(
                [ErrorCode::V1_VIDEO_ROOM_JOIN_USER_BANNED],
                Response::HTTP_FORBIDDEN
            );
        }

        $meetingWithOnlineUsers = $videoMeetingRepository->findMeetingWithOnlineUsers($room);
        if ($meetingWithOnlineUsers) {
            $onlineParticipants = $meetingWithOnlineUsers->getUniqueParticipants();

            $maxParticipantsCount = $room->maxParticipants ?? ConnectClub::MAX_VIDEO_ROOM_PARTICIPANTS;

            if ($onlineParticipants->count() >= $maxParticipantsCount) {
                $response = null;
                $responseCode = Response::HTTP_PRECONDITION_FAILED;

                if ($room->recoveryRoom) {
                    $response = [
                        'recoveryRoomName' => $room->recoveryRoom->community->name,
                        'recoveryRoomPassword' => $room->recoveryRoom->community->password,
                    ];
                    $responseCode = Response::HTTP_FOUND;
                }

                return $this->createJsonResponse(
                    ApiResponse::createErrorResponse(
                        $this->getRequestId(),
                        [ErrorCode::V1_VIDEO_ROOM_MAX_COUNT_PARTICIPANTS],
                        $response
                    ),
                    $responseCode
                );
            }
        }

        $isOwner = $currentUser && $room->community->owner->equals($currentUser);
        if ($room->doneAt !== null && ($isOwner || $room->alwaysReopen || $room->alwaysOnline)) {
            $room->doneAt = null;
            $room->config->dataTrackUrl = null;
            $entityManager->persist($room);
            $entityManager->flush();
        }

        if ($room->eventSchedule !== null &&
            !$room->eventSchedule->forOwnerTokens->isEmpty() &&
            !$currentUser
        ) {
            return $this->createErrorResponseWithData(
                'room_required_nft_wallet_'.$room->eventSchedule->id->toString(),
                ['eventId' => $room->eventSchedule->id->toString()],
                Response::HTTP_PAYMENT_REQUIRED
            );
        }

        if ($room->eventSchedule !== null &&
            $currentUser &&
            !$room->eventSchedule->forOwnerTokens->isEmpty() &&
            !$participantRepository->findOneBy(['event' => $room->eventSchedule, 'user' => $currentUser])
        ) {
            if (!$currentUser->wallet) {
                return $this->createErrorResponseWithData(
                    'room_required_nft_wallet_'.$room->eventSchedule->id->toString(),
                    ['eventId' => $room->eventSchedule->id->toString()],
                    Response::HTTP_PAYMENT_REQUIRED
                );
            }

            try {
                $tokenExists = false;

                foreach ($room->eventSchedule->forOwnerTokens as $eventScheduleToken) {
                    $token = $eventScheduleToken->token;

                    $balanceOf = $infuraClient->getSmartContract($token)->getBalance(
                        $token,
                        $currentUser->wallet,
                    );

                    if ($token->minAmount > $balanceOf) {
                        $eventLogManager->logEventCustomObject(
                            'min_amount_nft_token',
                            'user',
                            (string) $currentUser->id,
                            [
                                'balanceOf' => var_export($balanceOf, true),
                                'minAmount' => var_export($token->minAmount, true),
                            ]
                        );
                    } else {
                        $tokenExists = true;
                        break;
                    }
                }

                if (!$tokenExists) {
                    return $this->createErrorResponseWithData(
                        'room_required_nft_token_in_wallet_'.$room->eventSchedule->id->toString(),
                        ['eventId' => $room->eventSchedule->id->toString()],
                        Response::HTTP_PAYMENT_REQUIRED
                    );
                }
            } catch (Throwable $exception) {
                $logger->error($exception, ['exception' => $exception]);

                return $this->createErrorResponse(
                    'check_nft_token_error',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }

        if ($room->config->dataTrackUrl === null) {
            $room->config->dataTrackUrl = $settings->dataTrackUrl ?? null;
        }

        if ($room->config->dataTrackApiUrl === null) {
            $room->config->dataTrackApiUrl = $settings->dataTrackApiUrl ?? null;
        }

        $meeting = $room->getActiveMeeting();
        if (!$meeting && $room->doneAt === null) {
            $sid = uniqid();
            while ($videoMeetingRepository->findOneBy(['sid' => $sid])) {
                $sid = uniqid();
            }

            $meeting = new VideoMeeting($room, $sid, null, VideoRoomEvent::INITIATOR_JITSI);
            $meeting->isEmptyMeeting = true;
            $meeting->jitsiCounter = 0;
            $videoMeetingRepository->save($meeting);

            $eventLogManager->logEvent($meeting, 'create_meeting_by_token');
        }

        $expiresAt = ConnectClub::VIDEO_ROOM_SESSION_EXPIRES_AT;

        $time = new DateTimeImmutable();
        $tokenBuilder = (new Builder())
            ->issuedAt($time)
            ->canOnlyBeUsedAfter($time)
            ->expiresAt($time->modify('+'.$expiresAt.' seconds'))
            ->withClaim('conferenceGid', $room->community->name);

        if ($currentUser) {
            $tokenBuilder = $tokenBuilder
                ->withClaim('endpoint', (string) $this->getUser()->getId());
        }

        $token = $tokenBuilder->getToken(new Sha256(), new Key($_ENV['JWT_TOKEN_PRIVATE_KEY']));

        $isSpecialSpeaker = false;
        if ($currentUser && $room->eventSchedule) {
            $isSpecialSpeaker = !$room->eventSchedule->participants->filter(
                fn(EventScheduleParticipant $p) => $p->user->equals($currentUser) && $p->isSpecialGuest
            )->isEmpty();
        }

        return $this->handleResponse(
            new VideoRoomTokenResponse(
                (string) $token,
                $room,
                $room->community->name,
                $room->community->description,
                $room->id,
                $meeting ? $meeting->sid : '',
                $room->community->owner->getId(),
                $room->open,
                $currentUser && $room->community->isAdmin($currentUser),
                $isSpecialSpeaker
            ),
            Response::HTTP_OK
        );
    }

    /**
     * @SWG\Get(
     *     description="Get available always reopen video rooms",
     *     summary="Get available always reopen video rooms",
     *     tags={"Video Room"},
     *     @SWG\Parameter(
     *         in="query",
     *         name="userId",
     *         required=false,
     *         description="Specific owner id (for admin only)",
     *         type="integer"
     *     ),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(
     *     entityClass=VideoRoomResponse::class,
     *     pagination=true,
     *     paginationWithTotalCount=true,
     *     paginationByLastValue=true
     * )
     * @Route("/always-reopen", methods={"GET"})
     */
    public function alwaysReopen(
        Request $request,
        VideoRoomRepository $videoRoomRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $limit = $request->query->getInt('limit', 20) ?? 20;
        $lastValue = $request->query->get('lastValue');

        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $userId = $request->query->getInt('userId');
            if ($userId) {
                $user = $userRepository->find($userId);
            }
        }

        [$rooms, $lastValue, $total] = $videoRoomRepository->findAlwaysReopenRooms($user, $lastValue, $limit);
        $rooms = array_map(
            fn(VideoRoom $r) => new VideoRoomResponse($r),
            $rooms
        );

        return $this->handleResponse(new PaginatedResponseWithCount($rooms, $lastValue, $total));
    }
}
