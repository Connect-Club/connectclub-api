<?php

namespace App\Controller\V1\Land;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Land\CreateLandRequest;
use App\DTO\V1\Land\LandResponse;
use App\DTO\V1\Land\SectorListResponse;
use App\Entity\Land\Land;
use App\Entity\User;
use App\Repository\Land\LandRepository;
use App\Repository\Photo\ImageRepository;
use App\Repository\UserRepository;
use App\Repository\VideoChat\VideoRoomRepository;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/** @Route("/land") */
class AdminLandController extends BaseController
{
    private LandRepository $landRepository;
    private UserRepository $userRepository;
    private VideoRoomRepository $videoRoomRepository;
    private ImageRepository $imageRepository;

    public function __construct(
        LandRepository $landRepository,
        UserRepository $userRepository,
        VideoRoomRepository $videoRoomRepository,
        ImageRepository $imageRepository
    ) {
        $this->landRepository = $landRepository;
        $this->userRepository = $userRepository;
        $this->videoRoomRepository = $videoRoomRepository;
        $this->imageRepository = $imageRepository;
    }

    /**
     * @SWG\Get(
     *     description="Get all lands",
     *     summary="Get all lands",
     *     tags={"Land"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ListResponse(entityClass=SectorListResponse::class)
     * @Route("", methods={"GET"})
     */
    public function lands(): JsonResponse
    {
        $lands = $this->landRepository->findBy([], ['number' => 'ASC']);

        $response = [];
        foreach ($lands as $land) {
            if (!isset($response[$land->sector])) {
                $response[$land->sector] = new SectorListResponse();
                $response[$land->sector]->sector = $land->sector;
            }
            $response[$land->sector]->parcels[] = new LandResponse($land);
        }

        return $this->handleResponse(array_values($response));
    }

    /**
     * @SWG\Post(
     *     description="Create land",
     *     summary="Create land",
     *     tags={"Land"},
     *     @SWG\Parameter(name="body", in="body", @SWG\Schema(ref=@Model(type=CreateLandRequest::class))),
     *     @SWG\Response(response="201", description="OK")
     * )
     * @ViewResponse(entityClass=LandResponse::class)
     * @Route("", methods={"POST"})
     */
    public function createLand(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();

        /** @var CreateLandRequest $createLand */
        $createLand = $this->getEntityFromRequestTo($request, CreateLandRequest::class);

        $errors = $this->validate($createLand);
        if ($errors->count() > 0) {
            return $this->handleErrorResponse($errors);
        }

        if ($this->landRepository->findOneBy(['x' => $createLand->x, 'y' => $createLand->y])) {
            return $this->createErrorResponse('coordinates_already_reserved', Response::HTTP_CONFLICT);
        }

        $land = new Land($createLand->name, $createLand->x, $createLand->y, $createLand->sector, $user);
        $land->description = $createLand->description;
        $land->available = (bool) $createLand->available;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('number', 'number');
        $query = $em->createNativeQuery('SELECT nextval(\'land_number_seq\') AS number', $rsm)->getArrayResult();
        $land->number = $query[0]['number'] ?? 0;

        $owner = null;
        if ($createLand->ownerId !== null) {
            $owner = $this->userRepository->find($createLand->ownerId);
            if (!$owner) {
                return $this->createErrorResponse('owner_not_found', Response::HTTP_NOT_FOUND);
            }
            $land->owner = $owner;
        }

        if ($createLand->roomId !== null) {
            $room = $this->videoRoomRepository->findOneByName($createLand->roomId);

            if (!$room) {
                return $this->createErrorResponse('room_not_found', Response::HTTP_NOT_FOUND);
            } elseif ($owner && !$room->community->owner->equals($owner)) {
                return $this->createErrorResponse('room_owner_not_equals', Response::HTTP_NOT_FOUND);
            }

            $land->room = $room;
        }

        if ($createLand->imageId !== null) {
            $land->image = $this->imageRepository->find($createLand->imageId);
        }

        if ($createLand->thumbId !== null) {
            $land->thumb = $this->imageRepository->find($createLand->thumbId);
        }

        $this->landRepository->add($land);

        return $this->handleResponse(new LandResponse($land), Response::HTTP_CREATED);
    }

    /**
     * @SWG\Get(
     *     description="Get land",
     *     summary="Get land",
     *     tags={"Land"},
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=LandResponse::class)
     * @Route("/{id}", methods={"GET"})
     */
    public function getLand(string $id): JsonResponse
    {
        $land = $this->landRepository->find($id);

        if (!$land) {
            return $this->createErrorResponse('land_not_found', Response::HTTP_NOT_FOUND);
        }

        return $this->handleResponse(new LandResponse($land));
    }

    /**
     * @SWG\Patch(
     *     description="Update land",
     *     summary="Update land",
     *     tags={"Land"},
     *     @SWG\Parameter(name="body", in="body", @SWG\Schema(ref=@Model(type=CreateLandRequest::class))),
     *     @SWG\Response(response="200", description="OK")
     * )
     * @ViewResponse(entityClass=LandResponse::class)
     * @Route("/{id}", methods={"PATCH"})
     */
    public function updateLand(Request $request, string $id): JsonResponse
    {
        $land = $this->landRepository->find($id);
        if (!$land) {
            return $this->createErrorResponse('land_not_found', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $land->owner && $land->owner->equals($user);

        if (!$isAdmin && !$isOwner) {
            return $this->createErrorResponse('land_not_found', Response::HTTP_NOT_FOUND);
        }

        /** @var CreateLandRequest $createLand */
        $createLand = $this->getEntityFromRequestTo($request, CreateLandRequest::class);

        $errors = $this->validate($createLand);
        if ($errors->count() > 0) {
            return $this->handleErrorResponse($errors);
        }

        $landWithCoordinates = $this->landRepository->findOneBy(['x' => $createLand->x, 'y' => $createLand->y]);
        if ($landWithCoordinates && !$landWithCoordinates->id->equals($land->id)) {
            return $this->createErrorResponse('coordinates_already_reserved', Response::HTTP_CONFLICT);
        }

        if ($isAdmin) {
            if ($createLand->ownerId) {
                if (!$land->owner || $land->owner->id !== $createLand->ownerId) {
                    $owner = $this->userRepository->find($createLand->ownerId);
                    if (!$owner) {
                        return $this->createErrorResponse('owner_not_found', Response::HTTP_NOT_FOUND);
                    }
                    $land->owner = $owner;
                }
            } else {
                $land->owner = null;
            }
        }

        if ($createLand->roomId) {
            $room = $this->videoRoomRepository->findOneByName($createLand->roomId);

            if (!$room) {
                return $this->createErrorResponse('room_not_found', Response::HTTP_NOT_FOUND);
            } elseif ($land->owner && !$room->community->owner->equals($land->owner)) {
                return $this->createErrorResponse('room_owner_not_equals', Response::HTTP_NOT_FOUND);
            }

            $land->room = $room;
        } else {
            $land->room = null;
        }

        $land->x = (int) $createLand->x;
        $land->y = (int) $createLand->y;
        $land->name = $createLand->name;
        $land->description = $createLand->description;
        $land->sector = (int) $createLand->sector;
        $land->available = (bool) $createLand->available;

        $body = json_decode($request->getContent(), true);

        if ($createLand->imageId) {
            if (!$land->image || $land->image->id != $createLand->imageId) {
                $land->image = $this->imageRepository->find($createLand->imageId);
            }
        } elseif (array_key_exists('imageId', $body)) {
            $land->image = null;
        }

        if ($createLand->thumbId) {
            if (!$land->thumb || $land->thumb->id != $createLand->thumbId) {
                $land->thumb = $this->imageRepository->find($createLand->thumbId);
            }
        } elseif (array_key_exists('thumbId', $body)) {
            $land->thumb = null;
        }

        $this->landRepository->add($land);

        return $this->handleResponse(new LandResponse($land));
    }
}
