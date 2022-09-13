<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\Repository\VideoChat\VideoRoomRepository;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/event/statistic")
 */
class EventStatisticController extends BaseController
{
    private VideoRoomRepository $videoRoomRepository;

    public function __construct(VideoRoomRepository $videoRoomRepository)
    {
        $this->videoRoomRepository = $videoRoomRepository;
    }

    /**
     * @SWG\Get(
     *     description="Statistic video room",
     *     summary="Statistic video room",
     *     tags={"VideoRoom", "Statistic"},
     *     @SWG\Parameter(in="query", name="interval", type="integer", description="Interval", default="60"),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @Route("/{name}/online", methods={"GET"})
     */
    public function participantsCountPerInterval(Request $request, string $name): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        $interval = $request->query->getInt('interval');
        if (!$interval || $interval < 0) {
            $interval = 60;
        }

        $videoRoom = $this->videoRoomRepository->findOneByName($name);
        if (!$videoRoom) {
            return $this->createErrorResponse(ErrorCode::V1_VIDEO_ROOM_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->handleResponse([
            'statistics' => [
                'speakers' => $this->videoRoomRepository->fetchEventOnlineStatisticPerInterval(
                    $name,
                    $interval,
                    'speaker'
                ),
                'listeners' => $this->videoRoomRepository->fetchEventOnlineStatisticPerInterval(
                    $name,
                    $interval,
                    'listener'
                ),
                'all' => $this->videoRoomRepository->fetchEventOnlineStatisticPerInterval(
                    $name,
                    $interval,
                    'all'
                ),
            ],
            'interval' => $interval,
            'startedAt' => $videoRoom->startedAt,
            'endedAt' => $videoRoom->doneAt,
        ]);
    }
}
