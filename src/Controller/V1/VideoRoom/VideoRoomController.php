<?php

namespace App\Controller\V1\VideoRoom;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\VideoRoom\VideoRoomPublicResponse;
use App\Repository\VideoChat\BackgroundPhotoRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use Swagger\Annotations as SWG;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/video-room")
 */
class VideoRoomController extends BaseController
{
    private VideoRoomRepository $videoRoomRepository;
    private BackgroundPhotoRepository $backgroundPhotoRepository;

    public function __construct(
        VideoRoomRepository       $videoRoomRepository,
        BackgroundPhotoRepository $backgroundPhotoRepository
    ) {
        $this->videoRoomRepository = $videoRoomRepository;
        $this->backgroundPhotoRepository = $backgroundPhotoRepository;
    }

    /**
     *
     * @SWG\Patch(
     *     description="Convert video room to gallery",
     *     summary="Convert video room to gallery",
     *     tags={"Video Room"},
     *     @SWG\Response(response="200", description="Success update config"),
     *     @SWG\Response(response="404", description="Room not found"),
     *     @SWG\Response(response="403", description="Access denied"),
     * )
     * @Route("/{name}/gallery", methods={"PATCH"})
     */
    public function makeGallery(string $name): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        if (!$room = $this->videoRoomRepository->findOneByName($name)) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $galleryBackground = $this->backgroundPhotoRepository->find($_ENV['STAGE'] == 1 ? 4028 : 12056);
        if (!$galleryBackground) {
            return $this->createErrorResponse(
                ErrorCode::V1_VIDEO_ROOM_BACKGROUND_NOT_FOUND,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        $room->config->backgroundRoom = $galleryBackground;
        $room->alwaysReopen = true;

        $this->videoRoomRepository->save($room);

        return $this->handleResponse([]);
    }
}
