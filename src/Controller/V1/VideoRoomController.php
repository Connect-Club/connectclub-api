<?php

namespace App\Controller\V1;

use App\ConnectClub;
use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\VideoRoom\CreateVideoRoomRequest;
use App\DTO\V1\VideoRoom\ScreenShareLinkResponse;
use App\DTO\V1\VideoRoom\VideoRoomCreateResponse;
use App\DTO\V1\VideoRoom\VideoRoomDraftResponse;
use App\DTO\V1\VideoRoom\VideoRoomHistoryItemResponse;
use App\DTO\V1\VideoRoom\VideoRoomResponse;
use App\Entity\Community\Community;
use App\Entity\User;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomBan;
use App\Entity\VideoChat\VideoRoomHistory;
use App\Entity\VideoRoom\ScreenShareToken;
use App\Repository\Community\CommunityRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\BackgroundPhotoRepository;
use App\Repository\VideoChat\Object\ShareScreenObjectRepository;
use App\Repository\VideoChat\VideoMeetingRepository;
use App\Repository\VideoChat\VideoRoomBanRepository;
use App\Repository\VideoChat\VideoRoomHistoryRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Repository\VideoRoom\ScreenShareTokenRepository;
use App\Security\Voter\CommunityVoter;
use App\Security\Voter\VideoRoomVoter;
use App\Service\BanManager;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use MaxMind\Db\Reader;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Swagger\Annotations as SWG;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class RoomController.
 *
 * @Route("/video-room")
 */
class VideoRoomController extends BaseController
{
    private Reader $reader;
    private LoggerInterface $logger;
    private VideoRoomRepository $videoRoomRepository;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        Reader $reader,
        VideoRoomRepository $videoRoomRepository,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->reader = $reader;
        $this->logger = $logger;
        $this->videoRoomRepository = $videoRoomRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @deprecated use VideoRoomHistoryController::history instead
     * @SWG\Get(
     *     summary="Get history video room",
     *     description="Get history video room",
     *     @SWG\Response(response="200", description="Success response"),
     *     tags={"Video Room"},
     *     deprecated=true
     * )
     * @ListResponse(
     *     entityClass=VideoRoomHistoryItemResponse::class,
     *     pagination=true,
     *     paginationByLastValue=true,
     *     groups={"api.v1.video_room.history"}
     * )
     * @Route("/history", methods={"GET"})
     */
    public function history()
    {
        return $this->forward('App\Controller\V1\VideoRoomHistoryController::history');
    }

    /**
     * @deprecated use VideoRoomHistoryController::delete instead
     * @SWG\Delete(
     *     summary="Delete video room history",
     *     description="Delete video room history",
     *     @SWG\Response(response="200", description="Success response"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     @SWG\Response(response="404", description="History not found"),
     *     tags={"Video Room"},
     *     deprecated=true
     * )
     * @ViewResponse()
     * @Route("/history/{id}", methods={"DELETE"}, defaults={"id": "\d+"})
     */
    public function delete(int $id)
    {
        return $this->forward('App\Controller\V1\VideoRoomHistoryController::delete', ['id' => $id]);
    }

    /**
     * @SWG\Get(
     *     description="Drafts video room",
     *     summary="Drafts video room",
     *     @SWG\Response(response="200", description="Success getting drafts"),
     *     tags={"Video Room"},
     *     deprecated=true
     * )
     * @Nelmio\Security(name="oauth2BearerToken")
     * @ListResponse(entityClass=VideoRoomDraftResponse::class)
     * @Route("/drafts", methods={"GET"})
     */
    public function drafts()
    {
        return $this->forward('App\Controller\V1\VideoRoomDraftController::drafts');
    }

    /**
     * @SWG\Get(
     *     produces={"application/json"},
     *     description="Get information about video room",
     *     summary="Get information about video room",
     *     @SWG\Parameter(name="name", in="path", type="string", description="Room name"),
     *     @SWG\Response(response="200", description="Success get room"),
     *     @SWG\Response(response="404", description="Room not found"),
     *     @SWG\Parameter(
     *         in="query",
     *         name="password",
     *         required=false,
     *         type="string",
     *     ),
     *     tags={"Video Room"}
     * )
     * @ViewResponse(entityClass=VideoRoomResponse::class, groups={"default"})
     * @Route("/{name}", methods={"GET"})
     */
    public function getRoom(string $name, Request $request)
    {
        if (!$videoRoom = $this->videoRoomRepository->findOneByName($name)) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $serializationGroups = ['default', 'api.v1.video_room.name'];

        $requestPassword = $request->query->get('password');
        if ($requestPassword) {
            if ($videoRoom->community->password != $requestPassword) {
                return $this->handleResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
            }
        }

        return $this->handleResponse(new VideoRoomResponse($videoRoom), Response::HTTP_OK, $serializationGroups);
    }


    /**
     * @SWG\Get(
     *     produces={"application/json"},
     *     description="Get information about video room by sid",
     *     summary="Get information about video room by sid",
     *     @SWG\Parameter(name="sid", in="path", type="string", description="Room sid"),
     *     @SWG\Response(response="200", description="Success get room"),
     *     @SWG\Response(response="404", description="Room not found"),
     *     tags={"Video Room"}
     * )
     * @ViewResponse(entityClass=VideoRoom::class, groups={"v1.room.default", "v1.upload.default_photo"})
     * @Route("/sid/{sid}", methods={"GET"})
     */
    public function getRoomBySid(string $sid, VideoMeetingRepository $videoMeetingRepository)
    {
        if (!$videoMeeting = $videoMeetingRepository->findOneBy(['sid' => $sid])) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        return $this->handleResponse(
            new VideoRoomResponse($videoMeeting->videoRoom),
            Response::HTTP_OK,
            ['default', 'api.v1.video_room.sid']
        );
    }

    /**
     * @SWG\Post(
     *     description="Ban user in video room",
     *     summary="Ban user in video room",
     *     tags={"Video Room"},
     *     @SWG\Response(response="200", description="Success ban user"),
     *     @SWG\Response(response="404", description="Video room or abuser not found"),
     *     @SWG\Response(response="409", description="Conflict: Ban already exists"),
     * )
     * @Route("/ban/{name}/{abuserId}", methods={"POST"}, defaults={"abuserId": "\d+"})
     */
    public function ban(
        string $name,
        int $abuserId,
        UserRepository $userRepository,
        BanManager $banManager
    ) {
        if (!$videoRoom = $this->videoRoomRepository->findOneByName($name)) {
            return $this->createErrorResponse(
                [ErrorCode::V1_VIDEO_ROOM_NOT_FOUND],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$this->isGranted(CommunityVoter::COMMUNITY_BAN_USER, $videoRoom->community)) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        if (!$abuser = $userRepository->find($abuserId)) {
            return $this->createErrorResponse(
                [ErrorCode::V1_VIDEO_ROOM_BAN_ABUSER_NOT_FOUND],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$videoRoom->community->getParticipant($abuser)) {
            return $this->createErrorResponse(
                [ErrorCode::V1_COMMUNITY_PARTICIPANT_NOT_FOUND],
                Response::HTTP_NOT_FOUND
            );
        }

        $banManager->createBanUserInCommunityTransactions($videoRoom->community, $abuser)->run();

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Create sharing screen link",
     *     summary="Create sharing screen link",
     *     tags={"Video Room"},
     *     @SWG\Response(response="200", description="Success create sharing link")
     * )
     * @ViewResponse(
     *     entityClass=ScreenShareLinkResponse::class,
     *     errorCodesMap={
     *         {Response::HTTP_NOT_FOUND, ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, "Video room not found"}
     *     }
     * )
     * @Route("/{videoRoomName}/sharing", methods={"POST"})
     */
    public function sharingLink(ScreenShareTokenRepository $tokenRepository, string $videoRoomName): JsonResponse
    {
        $videoRoom = $this->videoRoomRepository->findOneByName($videoRoomName);
        if (!$videoRoom) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();

        $token = ConnectClub::generateString(7);
        while ($tokenRepository->findOneBy(['token' => $token])) {
            $token = ConnectClub::generateString(7);
        }

        $sharingToken = new ScreenShareToken($videoRoom, $user, $token);
        $tokenRepository->save($sharingToken);

        $link = $_ENV['SCREEN_SHARING_HOST'].$this->generateUrl('v1_short_link', ['token' => $sharingToken->token]);

        return $this->handleResponse(new ScreenShareLinkResponse($link));
    }
}
