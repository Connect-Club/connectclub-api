<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\VideoRoom\BackgroundWithObjectsResponse;
use App\Repository\VideoChat\BackgroundPhotoRepository;
use App\Swagger\ListResponse;
use Symfony\Component\Serializer\Serializer;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/video-room-background")
 */
class VideoRoomBackgroundController extends BaseController
{
    private BackgroundPhotoRepository $backgroundPhotoRepository;

    public function __construct(BackgroundPhotoRepository $backgroundPhotoRepository)
    {
        $this->backgroundPhotoRepository = $backgroundPhotoRepository;
    }

    /**
     * @SWG\Get(
     *     description="Background list with objects",
     *     summary="Background list with objects",
     *     @SWG\Response(response=200, description="Success response"),
     *     @SWG\Response(response=403, description="Access denied"),
     *     tags={"Video Room"},
     * )
     * @ListResponse(entityClass=BackgroundWithObjectsResponse::class)
     * @Route("", methods={"GET"})
     */
    public function backgrounds()
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $backgroundPhotos = $this->backgroundPhotoRepository->findBy(['uploadBy' => $this->getUser()]);
        } else {
            $backgroundPhotos = $this->backgroundPhotoRepository->findAll();
        }

        $response = [];
        foreach ($backgroundPhotos as $backgroundPhoto) {
            $response[] = new BackgroundWithObjectsResponse($backgroundPhoto);
        }

        return $this->handleResponse($response);
    }

    /**
     * @SWG\Patch(
     *     description="Update objects for background",
     *     summary="Update objects for background",
     *     deprecated=true,
     *     @SWG\Response(response=200, description="Success response"),
     *     @SWG\Response(response=403, description="Access denied"),
     *     @SWG\Parameter(
     *         in="body",
     *         name="body",
     *         schema=@SWG\Schema(example={
     *             {
     *                 "type": "fireplace",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "radius": 25.50,
     *                 "lottieSrc": "fireplace",
     *                 "soundSrc": "fireplace",
     *             },
     *             {
     *                 "type": "square_portal",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "name": "Square name",
     *             },
     *             {
     *                 "type": "main_spawn",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "video",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "radius": 25.50,
     *                 "length": 100,
     *                 "videoSrc": "asdsad",
     *             },
     *             {
     *                 "type": "portal",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *                 "name": "Portal video room name",
     *                 "password": "Portal video room password"
     *             },
     *             {
     *                 "type": "speaker_location",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "static_object",
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             },
     *             {
     *                 "type": "image",
     *                 "id": 1,
     *                 "location": {"x": 100, "y": 100},
     *                 "width": 100,
     *                 "height": 100,
     *             }
     *         })
     *     ),
     *     tags={"Video Room"},
     * )
     * @Route("/{id}/objects", methods={"PATCH"}, requirements={"id": "\d+"})
     */
    public function updateObjects(int $id)
    {
        return $this->forward('App\Controller\V1\VideoRoomObjectController::patchBackground', ['backgroundId' => $id]);
    }
}
