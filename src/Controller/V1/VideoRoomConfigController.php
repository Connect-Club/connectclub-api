<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\VideoRoom\UpdateVideoRoomConfigRequest;
use App\DTO\V1\VideoRoom\VideoRoomResponse;
use App\Entity\User;
use App\Entity\VideoChat\Location;
use App\Entity\VideoChat\Object\VideoRoomSpeakerLocationObject;
use App\Entity\VideoChat\VideoRoom;
use App\Entity\VideoChat\VideoRoomObject;
use App\Message\CheckAvatarPhotoTheHiveAiMessage;
use App\Repository\VideoChat\BackgroundPhotoRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Security\Voter\VideoRoomVoter;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class RoomController.
 *
 * @Route("/video-room")
 */
class VideoRoomConfigController extends BaseController
{
    /**
     * @SWG\Patch(
     *     produces={"application/json"},
     *     description="Update configuration video room",
     *     summary="Update configuration video room",
     *     @SWG\Parameter(name="name", in="path", type="string", description="Room name"),
     *     @SWG\Parameter(
     *         name="configuration",
     *         in="body",
     *         @SWG\Schema(ref=@Model(type=UpdateVideoRoomConfigRequest::class))
     *     ),
     *     @SWG\Response(response="200", description="Success update config"),
     *     @SWG\Response(response="404", description="Room not found"),
     *     @SWG\Response(response="403", description="Access denied"),
     *     tags={"Video Room"}
     * )
     * @ViewResponse(entityClass=VideoRoom::class, groups={"v1.room.default", "v1.upload.default_photo"})
     * @Route("/{name}/config", methods={"PATCH"})
     */
    public function updateConfig(
        string $name,
        Request $request,
        MessageBusInterface $bus,
        VideoRoomRepository $videoRoomRepository,
        BackgroundPhotoRepository $backgroundPhotoRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (!$videoRoom = $videoRoomRepository->findOneByName($name)) {
            return $this->createErrorResponse([ErrorCode::V1_VIDEO_ROOM_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(VideoRoomVoter::VIDEO_ROOM_CHANGE_CONFIGURATION, $videoRoom)) {
            return $this->createErrorResponse([ErrorCode::V1_ACCESS_DENIED], Response::HTTP_FORBIDDEN);
        }

        /** @var UpdateVideoRoomConfigRequest $update */
        $update = $this->getEntityFromRequestTo($request, UpdateVideoRoomConfigRequest::class);
        /** @var User $user */
        $user = $this->getUser();

        $videoRoom->alwaysReopen = true;

        if ($update->description !== null) {
            $videoRoom->community->description = $update->description;
        }

        if ($update->backgroundPhotoId) {
            if (!$backgroundPhoto = $backgroundPhotoRepository->find($update->backgroundPhotoId)) {
                return $this->createErrorResponse(
                    [ErrorCode::V1_VIDEO_ROOM_BACKGROUND_NOT_FOUND],
                    Response::HTTP_NOT_FOUND
                );
            }

            if ($videoRoom->config->backgroundRoom &&
                $videoRoom->config->backgroundRoom->id != $update->backgroundPhotoId) {
                $videoRoom->ignoredVideoRoomObjectsIds = []; //Reset ignored video room objects from background
            }

            $videoRoom->config->backgroundRoom = $backgroundPhoto;

            $bus->dispatch(new CheckAvatarPhotoTheHiveAiMessage(
                $backgroundPhoto->id,
                $backgroundPhoto->getResizerUrl(800, 800),
                $user->id,
                $videoRoom
            ));
        }

//        $videoRoom->config->backgroundRoomWidthMultiplier = $update->backgroundRoomWidthMultiplier;
//        $videoRoom->config->backgroundRoomHeightMultiplier = $update->backgroundRoomHeightMultiplier;
//        $videoRoom->config->initialRoomScale = $update->initialRoomScale;
//        $videoRoom->config->intervalToSendDataTrackInMilliseconds = $update->intervalToSendDataTrackInMilliseconds;
//        $videoRoom->config->maxRoomZoom = $update->maxRoomZoom;
//        $videoRoom->config->minRoomZoom = $update->minRoomZoom;
//        $videoRoom->config->videoBubbleSize = $update->videoBubbleSize;
//        $videoRoom->config->videoQuality->height = $update->videoQualityHeight;
//        $videoRoom->config->videoQuality->width = $update->videoQualityWidth;
//        $videoRoom->config->publisherRadarSize = $update->publisherRadarSize;

        $entityManager->persist($videoRoom);
        $entityManager->flush();

        return $this->handleResponse($videoRoom, Response::HTTP_OK, ['v1.room.default', 'v1.upload.default_photo']);
    }
}
