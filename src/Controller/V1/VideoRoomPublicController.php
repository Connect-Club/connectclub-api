<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\DTO\V1\VideoRoom\VideoRoomPublicResponse;
use App\Repository\VideoChat\VideoRoomRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Swagger\Annotations as SWG;
use App\Swagger\ViewResponse;
use Nelmio\ApiDocBundle\Annotation as Nelmio;

/**
 * @Route("/video-room-public")
 */
class VideoRoomPublicController extends BaseController
{
    /**
     * @SWG\Get(
     *     produces={"application/json"},
     *     description="Get public data about video room",
     *     summary="Get public data about video room",
     *     security=false,
     *     @SWG\Response(response="200", description="Public information"),
     *     tags={"Video Room"}
     * )
     * @Nelmio\Security(name="oauth2BearerToken")
     * @ViewResponse(entityClass=VideoRoomPublicResponse::class)
     * @Route("/{name}", methods={"GET"})
     */
    public function info(EntityManagerInterface $entityManager, string $name) : JsonResponse
    {
        $withSpeakers = true;
        $sql = <<<SQL
            SELECT vrc.with_speakers as withSpeakers
            FROM community c
            LEFT JOIN video_room vr ON vr.id = c.video_room_id
            LEFT JOIN video_room_config vrc ON vrc.id = vr.config_id
            WHERE c.name = :name
        SQL;
        $rsm = new ResultSetMapping();
        $room = $entityManager->createNativeQuery($sql, $rsm)
            ->setParameter('name', $name)
            ->getArrayResult();
        if (isset($room[0]['withSpeakers'])) {
            $withSpeakers = $room[0]['withSpeakers'];
        }

        return $this->handleResponse(new VideoRoomPublicResponse($withSpeakers));
    }
}
